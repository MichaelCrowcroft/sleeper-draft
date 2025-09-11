<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\PlayerSeasonSummary;
use App\Models\PlayerStats;
use Illuminate\Support\Facades\Artisan;

it('computes and stores 2024 season summaries and target share averages', function () {
    $player = Player::factory()->wideReceiver()->create([
        'team' => 'KC',
        'player_id' => 'p1',
    ]);

    // Two weeks of stats where team totals are known via other rows
    PlayerStats::create([
        'player_id' => 'p1',
        'game_date' => '2024-09-08',
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'team' => 'KC',
        'stats' => [
            'pts_ppr' => 20,
            'gms_active' => 1,
            'rec_tgt' => 10,
        ],
    ]);

    // Teammate to contribute to team target total
    PlayerStats::create([
        'player_id' => 'teammate',
        'game_date' => '2024-09-08',
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'team' => 'KC',
        'stats' => [
            'rec_tgt' => 10,
        ],
    ]);

    PlayerStats::create([
        'player_id' => 'p1',
        'game_date' => '2024-09-15',
        'season' => 2024,
        'week' => 2,
        'season_type' => 'regular',
        'team' => 'KC',
        'stats' => [
            'pts_ppr' => 10,
            'gms_active' => 1,
            'rec_tgt' => 5,
        ],
    ]);

    PlayerStats::create([
        'player_id' => 'teammate',
        'game_date' => '2024-09-15',
        'season' => 2024,
        'week' => 2,
        'season_type' => 'regular',
        'team' => 'KC',
        'stats' => [
            'rec_tgt' => 5,
        ],
    ]);

    // Run command
    Artisan::call('season-summaries:compute', ['--season' => 2024, '--no-interaction' => true]);

    $summary = PlayerSeasonSummary::where('player_id', 'p1')->where('season', 2024)->first();
    expect($summary)->not->toBeNull();

    // total points 30, games 2, avg 15
    expect($summary->total_points)->toEqual(30.0);
    expect($summary->average_points_per_game)->toEqual(15.0);
    // target share: week1 10/(10+10)=0.5, week2 5/(5+5)=0.5 => 50%
    expect($summary->target_share_avg)->toEqualWithDelta(50.0, 0.0001);
});
