<?php

declare(strict_types=1);

use App\Models\ApiAnalytics;
use App\Models\Player;
use App\Models\PlayerProjections;
use App\Models\PlayerStats;
use Illuminate\Support\Str;

it('returns players with 2024 stats summary and 2025 projections summary', function () {
    ApiAnalytics::query()->delete();

    // Create players and attach stats/projections
    $players = Player::factory()->count(3)->create();

    foreach ($players as $player) {
        // Two weeks of 2024 stats with pts_ppr
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
        PlayerStats::create([
            'player_id' => $player->player_id,
            'game_date' => '2024-09-15',
            'season' => 2024,
            'week' => 2,
            'season_type' => 'regular',
            'sport' => 'nfl',
            'company' => 'sleeper',
            'team' => $player->team,
            'opponent' => 'KC',
            'game_id' => (string) Str::uuid(),
            'stats' => ['pts_ppr' => 20.0, 'gms_active' => 1],
            'raw' => [],
        ]);

        // Two weeks of 2025 projections with pts_ppr
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
            'game_id' => (string) Str::uuid(),
            'pts_ppr' => 15.0,
            'gms_active' => 1,
        ]);
        PlayerProjections::create([
            'player_id' => $player->player_id,
            'game_date' => '2025-09-14',
            'season' => 2025,
            'week' => 2,
            'season_type' => 'regular',
            'sport' => 'nfl',
            'company' => 'sleeper',
            'team' => $player->team,
            'opponent' => 'KC',
            'game_id' => (string) Str::uuid(),
            'pts_ppr' => 25.0,
            'gms_active' => 1,
        ]);
    }

    $response = $this->postJson('/api/mcp/tools/fetch-players-season-data', [
        'limit' => 5,
    ]);

    $response->assertSuccessful();
    $json = $response->json();

    expect($json['success'] ?? null)->toBeTrue();
    expect($json['players'])->toBeArray();
    expect(count($json['players']))->toBeGreaterThan(0);

    $first = $json['players'][0];
    expect($first)->toHaveKey('season_2024_summary');
    expect($first['season_2024_summary'])->toHaveKeys(['total_points', 'average_points_per_game']);
    expect($first)->toHaveKey('season_2025_projection_summary');
    expect($first['season_2025_projection_summary'])->toHaveKeys(['total_points', 'average_points_per_game']);

    $record = ApiAnalytics::query()
        ->where('endpoint', 'api/mcp/tools/fetch-players-season-data')
        ->latest('id')
        ->first();

    expect($record)->not->toBeNull();
    expect($record->tool_name)->toBe('mcp_fantasy-football-mcp_fetch-players-season-data');
    expect($record->endpoint_category)->toBe('mcp_tools_api');
});
