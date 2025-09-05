<?php

declare(strict_types=1);

use App\Models\ApiAnalytics;
use App\Models\Player;
use App\Models\PlayerProjections;
use App\Models\PlayerStats;
use Illuminate\Support\Str;

it('returns a single player by player_id with summaries', function () {
    ApiAnalytics::query()->delete();

    $player = Player::factory()->create(['full_name' => 'Test Alpha']);

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
        'game_id' => (string) Str::uuid(),
        'pts_ppr' => 15.0,
        'gms_active' => 1,
    ]);

    $resp = $this->postJson('/api/mcp/tools/fetch-player-season-data', [
        'player_id' => $player->player_id,
    ]);

    $resp->assertSuccessful();
    $json = $resp->json();
    expect($json['mode'])->toBe('by_id');
    expect($json['count'])->toBe(1);
    expect($json['players'][0])->toHaveKey('season_2024_summary');
    expect($json['players'][0])->toHaveKey('season_2025_projection_summary');

    $record = ApiAnalytics::query()
        ->where('endpoint', 'api/mcp/tools/fetch-player-season-data')
        ->latest('id')
        ->first();
    expect($record)->not->toBeNull();
    expect($record->tool_name)->toBe('mcp_fantasy-football-mcp_fetch-player-season-data');
});

it('returns multiple players by name search when multiple match', function () {
    ApiAnalytics::query()->delete();

    $p1 = Player::factory()->create(['full_name' => 'John Sample']);
    $p2 = Player::factory()->create(['full_name' => 'Johnny Sampleton']);

    PlayerStats::create([
        'player_id' => $p1->player_id,
        'game_date' => '2024-09-08',
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'sport' => 'nfl',
        'company' => 'sleeper',
        'team' => $p1->team,
        'opponent' => 'BUF',
        'game_id' => (string) Str::uuid(),
        'stats' => ['pts_ppr' => 12.0, 'gms_active' => 1],
        'raw' => [],
    ]);
    PlayerProjections::create([
        'player_id' => $p2->player_id,
        'game_date' => '2025-09-07',
        'season' => 2025,
        'week' => 1,
        'season_type' => 'regular',
        'sport' => 'nfl',
        'company' => 'sleeper',
        'team' => $p2->team,
        'opponent' => 'BUF',
        'game_id' => (string) Str::uuid(),
        'pts_ppr' => 14.0,
        'gms_active' => 1,
    ]);

    $resp = $this->postJson('/api/mcp/tools/fetch-player-season-data', [
        'name' => 'John',
    ]);

    $resp->assertSuccessful();
    $json = $resp->json();
    expect($json['mode'])->toBe('by_name');
    expect($json['count'])->toBeGreaterThanOrEqual(2);
});
