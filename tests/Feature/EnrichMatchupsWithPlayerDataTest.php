<?php

use App\Actions\Matchups\EnrichMatchupsWithPlayerData;
use App\Models\Player;
use App\Models\PlayerProjections;
use App\Models\PlayerSeasonSummary;
use App\Models\PlayerStats;

it('adds per-player 90% range and volatility info', function () {
    $season = 2025;
    $week = 3;

    // Create a player with prior season summary including volatility
    $player = Player::factory()->create([
        'position' => 'WR',
        'fantasy_positions' => ['WR'],
    ]);

    PlayerSeasonSummary::query()->create([
        'player_id' => $player->player_id,
        'season' => $season - 1,
        'total_points' => 200.0,
        'min_points' => 5.0,
        'max_points' => 30.0,
        'average_points_per_game' => 12.5,
        'stddev_below' => 10.0,
        'stddev_above' => 15.0,
        'games_active' => 16,
        'snap_percentage_avg' => 75.0,
        'position_rank' => 24,
        'target_share_avg' => 18.0,
        'volatility' => [
            'coefficient_of_variation' => 0.25,
            'steadiness_score' => 4.0,
        ],
    ]);

    // Create a projection for this week
    PlayerProjections::factory()->create([
        'player_id' => $player->player_id,
        'season' => $season,
        'week' => $week,
        'pts_ppr' => 14.0,
        'gms_active' => 1,
    ]);

    // No actual stats yet for the current week
    PlayerStats::factory()->create([
        'player_id' => $player->player_id,
        'season' => $season,
        'week' => $week,
        'stats' => [
            // leave pts_ppr null to force projection path
        ],
    ]);

    $matchups = [
        1 => [
            [
                'owner_id' => 'a',
                'starters' => [$player->player_id],
                'players' => [$player->player_id],
            ],
            [
                'owner_id' => 'b',
                'starters' => [],
                'players' => [],
            ],
        ],
    ];

    $result = (new EnrichMatchupsWithPlayerData)->execute($matchups, $season, $week);

    expect($result[1][0]['starters'][0]['player_id'])->toBe($player->player_id);
    $enriched = $result[1][0]['starters'][0];

    // Has volatility copied from summary
    expect($enriched['volatility'])->toBeArray();
    expect($enriched['volatility']['coefficient_of_variation'])->toBeFloat();

    // Has projected 90% range
    expect($enriched['projected_range_90'])->toBeArray();
    expect($enriched['projected_range_90']['lower_90'])->toBeFloat();
    expect($enriched['projected_range_90']['upper_90'])->toBeFloat();
    expect($enriched['projected_range_90']['base'])->toEqual(14.0);
});
