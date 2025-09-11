<?php

use App\Models\Player;
use App\Models\PlayerStats;

test('command updates weekly rankings for a season (single week data)', function () {
    // Create test players
    $player1 = Player::factory()->create(['position' => 'RB', 'player_id' => 'test_player_1']);
    $player2 = Player::factory()->create(['position' => 'RB', 'player_id' => 'test_player_2']);
    $player3 = Player::factory()->create(['position' => 'RB', 'player_id' => 'test_player_3']);

    // Create player stats with different PPR points (player2 should rank first)
    PlayerStats::create([
        'player_id' => $player1->player_id,
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'game_date' => '2024-09-08',
        'stats' => ['pts_ppr' => 15.5],
    ]);

    PlayerStats::create([
        'player_id' => $player2->player_id,
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'game_date' => '2024-09-08',
        'stats' => ['pts_ppr' => 22.3], // Highest score
    ]);

    PlayerStats::create([
        'player_id' => $player3->player_id,
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'game_date' => '2024-09-08',
        'stats' => ['pts_ppr' => 8.7],
    ]);

    // Run the command (season only)
    $this->artisan('rankings:update-weekly --season=2024')
        ->expectsOutput('Weekly rankings update completed!')
        ->assertExitCode(0);

    // Assert rankings are correct
    $stats1 = PlayerStats::where('player_id', $player1->player_id)->first();
    $stats2 = PlayerStats::where('player_id', $player2->player_id)->first();
    $stats3 = PlayerStats::where('player_id', $player3->player_id)->first();

    expect($stats2->weekly_ranking)->toBe(1); // Highest PPR points
    expect($stats1->weekly_ranking)->toBe(2); // Middle PPR points
    expect($stats3->weekly_ranking)->toBe(3); // Lowest PPR points
});

test('command updates weekly rankings for entire season across multiple weeks', function () {
    // Create test player
    $player = Player::factory()->create(['position' => 'WR', 'player_id' => 'test_player_wr']);

    // Create player stats for multiple weeks
    PlayerStats::create([
        'player_id' => $player->player_id,
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'game_date' => '2024-09-08',
        'stats' => ['pts_ppr' => 12.0],
    ]);

    PlayerStats::create([
        'player_id' => $player->player_id,
        'season' => 2024,
        'week' => 2,
        'season_type' => 'regular',
        'game_date' => '2024-09-15',
        'stats' => ['pts_ppr' => 18.5],
    ]);

    // Run the command for entire season
    $this->artisan('rankings:update-weekly --season=2024')
        ->expectsOutput('Weekly rankings update completed!')
        ->assertExitCode(0);

    // Assert both weeks have rankings
    $stats1 = PlayerStats::where('player_id', $player->player_id)->where('week', 1)->first();
    $stats2 = PlayerStats::where('player_id', $player->player_id)->where('week', 2)->first();

    expect($stats1->weekly_ranking)->toBe(1);
    expect($stats2->weekly_ranking)->toBe(1);
});

test('weekly_rank accessor returns stored weekly_ranking when available', function () {
    $player = Player::factory()->create(['position' => 'QB', 'player_id' => 'test_qb']);

    $stats = PlayerStats::create([
        'player_id' => $player->player_id,
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'game_date' => '2024-09-08',
        'stats' => ['pts_ppr' => 25.0],
        'weekly_ranking' => 5,
    ]);

    // The weekly_rank accessor should return the stored weekly_ranking
    expect($stats->weekly_rank)->toBe(5);
});

test('weekly_rank accessor falls back to computed weekly_rank from scope', function () {
    $player = Player::factory()->create(['position' => 'TE', 'player_id' => 'test_te']);

    PlayerStats::create([
        'player_id' => $player->player_id,
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'game_date' => '2024-09-08',
        'stats' => ['pts_ppr' => 12.0],
        // No weekly_ranking stored
    ]);

    // Use the scope that computes weekly_rank on the fly
    $stats = PlayerStats::withWeeklyRank(2024, 1)->where('player_stats.player_id', $player->player_id)->first();

    // The weekly_rank should be computed (should be 1 since it's the only TE)
    expect($stats->weekly_rank)->toBe(1);
});
