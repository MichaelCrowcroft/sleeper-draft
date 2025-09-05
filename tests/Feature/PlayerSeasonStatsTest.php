<?php

declare(strict_types=1);

use App\Http\Resources\PlayerResource;
use App\Models\Player;
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

it('includes season_2024_stats in PlayerResource when relation is loaded', function () {
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
        ],
    ]);

    PlayerStats::factory()->create([
        'player_id' => $player->player_id,
        'season' => $season,
        'week' => 2,
        'stats' => [
            'rec' => 3,
        ],
    ]);

    // Eager-load stats2024 relation
    $player->load('stats2024');
    $request = Request::create('/', 'GET');
    $resource = (new PlayerResource($player))->toArray($request);

    expect($resource)
        ->toHaveKey('season_2024_stats')
        ->and($resource['season_2024_stats']['rec'] ?? null)->toBe(5.0);
});
