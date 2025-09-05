<?php

declare(strict_types=1);

use App\Models\{Player, PlayerProjections, PlayerStats};
use Illuminate\Support\Str;

it('paginates using cursor with nextCursor present until end', function () {
    // Create 3 players with minimal stats/projections so resource renders
    $players = Player::factory()->count(3)->create();
    foreach ($players as $idx => $p) {
        PlayerStats::create([
            'player_id' => $p->player_id,
            'game_date' => '2024-09-0'.(($idx % 2) + 8),
            'season' => 2024,
            'week' => 1,
            'season_type' => 'regular',
            'sport' => 'nfl',
            'company' => 'sleeper',
            'team' => $p->team,
            'opponent' => 'BUF',
            'game_id' => (string) Str::uuid(),
            'stats' => ['pts_ppr' => 10 + $idx, 'gms_active' => 1],
            'raw' => [],
        ]);
        PlayerProjections::create([
            'player_id' => $p->player_id,
            'game_date' => '2025-09-07',
            'season' => 2025,
            'week' => 1,
            'season_type' => 'regular',
            'sport' => 'nfl',
            'company' => 'sleeper',
            'team' => $p->team,
            'opponent' => 'BUF',
            'game_id' => (string) Str::uuid(),
            'pts_ppr' => 15.0,
            'gms_active' => 1,
        ]);
    }

    // First page with limit 2
    $r1 = $this->postJson('/api/mcp/tools/fetch-players-season-data', [
        'limit' => 2,
    ])->assertSuccessful()->json();

    expect($r1['count'])->toBe(2);
    expect($r1)->toHaveKey('nextCursor');
    expect($r1['nextCursor'])->not->toBeNull();

    // Second page using cursor
    $r2 = $this->postJson('/api/mcp/tools/fetch-players-season-data', [
        'cursor' => $r1['nextCursor'],
    ])->assertSuccessful()->json();

    expect($r2['count'])->toBe(1);
    // End of results => nextCursor should be missing or null
    // We tolerate either missing or null
    expect(array_key_exists('nextCursor', $r2) ? $r2['nextCursor'] : null)->toBeNull();
});
