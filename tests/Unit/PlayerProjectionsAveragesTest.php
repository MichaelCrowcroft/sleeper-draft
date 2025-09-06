<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\PlayerProjections;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

it('computes projected points for a given week and per-game averages for 2025', function () {
    // Create a player with a stable Sleeper player_id
    /** @var Player $player */
    $player = Player::factory()->create([
        'player_id' => 'plyr_123',
        'active' => true,
    ]);

    // Insert two weeks of 2025 projections using flattened columns (SQLite schema)
    PlayerProjections::create([
        'player_id' => $player->player_id,
        'game_date' => Carbon::parse('2025-09-07'),
        'season' => 2025,
        'week' => 1,
        'season_type' => 'regular',
        'sport' => 'nfl',
        'company' => 'sleeper',
        'team' => 'KC',
        'opponent' => 'BUF',
        'pts_ppr' => 15.2,
        'gms_active' => 1,
        'pass_att' => 10,
        'pass_cmp' => 6,
    ]);

    PlayerProjections::create([
        'player_id' => $player->player_id,
        'game_date' => Carbon::parse('2025-09-14'),
        'season' => 2025,
        'week' => 2,
        'season_type' => 'regular',
        'sport' => 'nfl',
        'company' => 'sleeper',
        'team' => 'KC',
        'opponent' => 'SF',
        'pts_ppr' => 12.8,
        'gms_active' => 1,
        'pass_att' => 12,
        'pass_cmp' => 7,
    ]);

    // Check projected points for week 1
    $week1 = $player->getProjectedPointsForWeek(2025, 1);
    expect($week1)->toBeFloat()->toBe(15.2);

    // Compute averages
    $avg = $player->getSeason2025ProjectionsAverages();

    // With 2 games, averages should be mean per game
    // pts_ppr: (15.2 + 12.8) / 2 = 14.0
    expect($avg['pts_ppr'] ?? null)->toBeFloat()->toEqualWithDelta(14.0, 0.0001);

    // Other flattened averages may not be included; ensure array is not empty
    expect($avg)->not->toBeEmpty();
});
