<?php

declare(strict_types=1);

use App\Actions\Players\PlayerTableQuery;
use App\Models\Player;
use App\Models\PlayerStats;
use App\Models\PlayerProjections;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('computes weekly rank and joins player position for stats', function () {
    $season = 2024;
    $week = 3;

    $p1 = Player::factory()->wideReceiver()->create();
    $p2 = Player::factory()->wideReceiver()->create();
    $p3 = Player::factory()->wideReceiver()->create();

    PlayerStats::factory()->create([
        'player_id' => $p1->player_id,
        'season' => $season,
        'week' => $week,
        'stats' => ['pts_ppr' => 15.0],
    ]);
    PlayerStats::factory()->create([
        'player_id' => $p2->player_id,
        'season' => $season,
        'week' => $week,
        'stats' => ['pts_ppr' => 20.0],
    ]);
    PlayerStats::factory()->create([
        'player_id' => $p3->player_id,
        'season' => $season,
        'week' => $week,
        'stats' => ['pts_ppr' => 5.0],
    ]);

    $rows = PlayerStats::query()->withWeeklyRank($season, $week)->get();

    $top = $rows->firstWhere('player_id', $p2->player_id);
    expect($top->weekly_rank)->toBe(1);
    expect($top->player_position)->toBe('WR');
});

it('computes weekly rank and joins player position for projections', function () {
    $season = 2025;
    $week = 1;

    $p1 = Player::factory()->runningBack()->create();
    $p2 = Player::factory()->runningBack()->create();

    PlayerProjections::factory()->create([
        'player_id' => $p1->player_id,
        'season' => $season,
        'week' => $week,
        'pts_ppr' => 12.5,
    ]);
    PlayerProjections::factory()->create([
        'player_id' => $p2->player_id,
        'season' => $season,
        'week' => $week,
        'pts_ppr' => 18.2,
    ]);

    $rows = PlayerProjections::query()->withWeeklyRank($season, $week)->get();

    $top = $rows->firstWhere('player_id', $p2->player_id);
    expect($top->weekly_rank)->toBe(1);
    expect($top->player_position)->toBe('RB');
});

it('builds a query with filters and sorting using model scopes', function () {
    $qb = Player::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Able', 'position' => 'QB', 'team' => 'GB', 'active' => true, 'adp' => 10.0]);
    $rb = Player::factory()->create(['first_name' => 'Barry', 'last_name' => 'Back', 'position' => 'RB', 'team' => 'GB', 'active' => true, 'adp' => 20.0]);
    $wr = Player::factory()->create(['first_name' => 'Carl', 'last_name' => 'Catch', 'position' => 'WR', 'team' => 'KC', 'active' => false, 'adp' => 15.0]);

    $sut = app(PlayerTableQuery::class);

    $q = $sut->build([
        'search' => 'a',
        'position' => 'RB',
        'team' => 'GB',
        'activeOnly' => true,
        'excludePlayerIds' => [],
        'sortBy' => 'adp',
        'sortDirection' => 'asc',
    ]);

    $rows = $q->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->player_id)->toBe($rb->player_id);
});
