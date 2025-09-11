<?php

declare(strict_types=1);

use App\Http\Resources\PlayerResource;
use App\Models\Player;
use App\Models\PlayerProjections;
use App\Models\PlayerSeasonSummary;
use App\Models\PlayerStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

it('aggregates season stats by summing weekly numeric metrics', function () {
    /** @var Player $player */
    $player = Player::factory()->create();

    $season = 2024;

    // Create three weeks of stats with deterministic numbers for easy assertions
    PlayerStats::factory()->create([
        'player_id' => $player->player_id,
        'season' => $season,
        'week' => 1,
        'stats' => [
            'rec' => 5,
            'rec_yd' => 50,
            'rush_td' => 1,
            'note' => 'ignored', // non-numeric should be ignored
        ],
    ]);

    PlayerStats::factory()->create([
        'player_id' => $player->player_id,
        'season' => $season,
        'week' => 2,
        'stats' => [
            'rec' => 7,
            'rec_yd' => 70,
            'rush_td' => 0,
        ],
    ]);

    PlayerStats::factory()->create([
        'player_id' => $player->player_id,
        'season' => $season,
        'week' => 3,
        'stats' => [
            'rec' => 3,
            'rec_yd' => 30,
            'rush_td' => 2,
        ],
    ]);

    $totals = $player->calculateSeasonStatTotals($season);

    expect($totals['rec'] ?? null)->toBe(15.0)
        ->and($totals['rec_yd'] ?? null)->toBe(150.0)
        ->and($totals['rush_td'] ?? null)->toBe(3.0)
        ->and(isset($totals['note']))->toBeFalse();
});

it('includes season_2025_projection_summary in PlayerResource when relation is loaded', function () {
    /** @var Player $player */
    $player = Player::factory()->create([
        'full_name' => 'Proj Player',
    ]);

    $season = 2025;

    PlayerProjections::factory()->create([
        'player_id' => $player->player_id,
        'season' => $season,
        'week' => 1,
        'pts_ppr' => 10.0,
        'gms_active' => 1,
    ]);

    PlayerProjections::factory()->create([
        'player_id' => $player->player_id,
        'season' => $season,
        'week' => 2,
        'pts_ppr' => 20.0,
        'gms_active' => 1,
    ]);

    // Eager-load projections2025 relation
    $player->load('projections2025');
    $request = Request::create('/', 'GET');
    $resource = (new PlayerResource($player))->toArray($request);

    expect($resource)
        ->toHaveKey('season_2025_projection_summary')
        ->and($resource['season_2025_projection_summary']['total_points'] ?? null)->toBe(30.0)
        ->and($resource['season_2025_projection_summary']['min_points'] ?? null)->toBe(10.0)
        ->and($resource['season_2025_projection_summary']['max_points'] ?? null)->toBe(20.0)
        ->and($resource['season_2025_projection_summary']['games'] ?? null)->toBe(2)
        ->and($resource['season_2025_projection_summary']['average_points_per_game'] ?? null)->toBe(15.0);
});

it('includes season_2024_summary in PlayerResource when relation is loaded', function () {
    /** @var Player $player */
    $player = Player::factory()->create([
        'full_name' => 'Test Player',
    ]);

    $season = 2024;

    PlayerStats::factory()->create([
        'player_id' => $player->player_id,
        'season' => $season,
        'week' => 1,
        'stats' => [
            'rec' => 2,
            'pts_ppr' => 2.0,
            'gms_active' => 1,
        ],
    ]);

    PlayerStats::factory()->create([
        'player_id' => $player->player_id,
        'season' => $season,
        'week' => 2,
        'stats' => [
            'rec' => 3,
            'pts_ppr' => 3.0,
            'gms_active' => 1,
        ],
    ]);

    // Persist cached season summary (simulating precomputed cache)
    PlayerSeasonSummary::create([
        'player_id' => $player->player_id,
        'season' => 2024,
        'total_points' => 5.0,
        'min_points' => 2.0,
        'max_points' => 3.0,
        'average_points_per_game' => 2.5,
        'stddev_below' => 2.0,
        'stddev_above' => 3.0,
        'games_active' => 2,
        'snap_percentage_avg' => null,
        'position_rank' => null,
        'target_share_avg' => null,
        'volatility' => null,
    ]);

    // Eager-load cached relation
    $player->load('seasonSummaries');
    $request = Request::create('/', 'GET');
    $resource = (new PlayerResource($player))->toArray($request);

    expect($resource)
        ->toHaveKey('season_2024_summary')
        ->and($resource['season_2024_summary']['total_points'] ?? null)->toBe(5.0)
        ->and($resource['season_2024_summary']['min_points'] ?? null)->toBe(2.0)
        ->and($resource['season_2024_summary']['max_points'] ?? null)->toBe(3.0)
        ->and($resource['season_2024_summary']['games_active'] ?? null)->toBe(2)
        ->and($resource['season_2024_summary']['average_points_per_game'] ?? null)->toBe(2.5)
        ->and($resource['season_2024_summary']['stddev_below'] ?? null)->toBe(2.0)
        ->and($resource['season_2024_summary']['stddev_above'] ?? null)->toBe(3.0);
});
