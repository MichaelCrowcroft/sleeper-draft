<?php

declare(strict_types=1);

use App\MCP\Tools\FetchFreeAgentsPlayersSeasonDataTool;
use App\Models\Player;
use App\Models\PlayerProjections;
use App\Models\PlayerStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tool = new FetchFreeAgentsPlayersSeasonDataTool;
});

it('excludes rostered players for the given league', function () {
    // Create three players in DB
    $p1 = Player::factory()->create(['player_id' => '11111', 'team' => 'BUF', 'position' => 'QB']);
    $p2 = Player::factory()->create(['player_id' => '22222', 'team' => 'KC', 'position' => 'WR']);
    $p3 = Player::factory()->create(['player_id' => '33333', 'team' => 'PHI', 'position' => 'RB']);

    // Minimal stats/projections so the resource composes summaries
    foreach ([$p1, $p2, $p3] as $player) {
        PlayerStats::create([
            'player_id' => $player->player_id,
            'game_date' => '2024-09-08',
            'season' => 2024,
            'week' => 1,
            'season_type' => 'regular',
            'sport' => 'nfl',
            'company' => 'sleeper',
            'team' => $player->team,
            'opponent' => 'BUF',
            'game_id' => (string) Str::uuid(),
            'stats' => ['pts_ppr' => 10.0, 'gms_active' => 1],
            'raw' => [],
        ]);

        PlayerProjections::create([
            'player_id' => $player->player_id,
            'game_date' => '2025-09-07',
            'season' => 2025,
            'week' => 1,
            'season_type' => 'regular',
            'sport' => 'nfl',
            'company' => 'sleeper',
            'team' => $player->team,
            'opponent' => 'BUF',
            'pts_ppr' => 12.0,
            'gms_active' => 1,
        ]);
    }

    // Mock Sleeper league rosters: rostered players include 11111 and 22222
    Http::fake([
        'https://api.sleeper.app/v1/league/league_abc/rosters' => Http::response([
            ['roster_id' => 1, 'owner_id' => 'user1', 'players' => ['11111'], 'starters' => ['11111']],
            ['roster_id' => 2, 'owner_id' => 'user2', 'players' => ['22222'], 'starters' => []],
        ]),
        '*' => Http::response([], 200),
    ]);

    $result = $this->tool->execute([
        'league_id' => 'league_abc',
        'limit' => 10,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['metadata']['league_id'])->toBe('league_abc');

    // Only player 33333 should remain as a free agent
    $ids = collect($result['players'])->pluck('player_id')->all();
    expect($ids)->not->toContain('11111');
    expect($ids)->not->toContain('22222');
    expect($ids)->toContain('33333');
});

it('supports position filter and pagination', function () {
    // Create multiple WRs and RBs
    $players = Player::factory()->count(5)->create(['position' => 'WR']);
    Player::factory()->count(3)->create(['position' => 'RB']);

    Http::fake([
        // No rostered players
        'https://api.sleeper.app/v1/league/league_abc/rosters' => Http::response([]),
        '*' => Http::response([], 200),
    ]);

    $first = $this->tool->execute([
        'league_id' => 'league_abc',
        'position' => 'WR',
        'limit' => 3,
        'offset' => 0,
    ]);

    expect($first['success'])->toBeTrue();
    expect($first['count'])->toBe(3);
    expect($first['nextCursor'])->not->toBeNull();

    $next = $this->tool->execute([
        'cursor' => $first['nextCursor'],
    ]);

    // Remaining WRs should be returned (2 more)
    expect($next['success'])->toBeTrue();
    expect($next['count'])->toBeGreaterThanOrEqual(1);
});
