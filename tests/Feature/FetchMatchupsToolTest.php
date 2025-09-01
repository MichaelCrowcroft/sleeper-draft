<?php

declare(strict_types=1);

use App\MCP\Tools\FetchMatchupsTool;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tool = new FetchMatchupsTool;
});

it('has correct tool properties', function () {
    expect($this->tool->name())->toBe('fetch-matchups');
    expect($this->tool->isStreaming())->toBeFalse();
    expect($this->tool->description())->toContain('Fetch matchups for a league');
});

it('validates required fields', function () {
    expect(fn () => $this->tool->execute([]))
        ->toThrow(JsonRpcErrorException::class, 'Validation failed');
});

it('validates league_id is required', function () {
    expect(fn () => $this->tool->execute(['week' => 1]))
        ->toThrow(JsonRpcErrorException::class, 'Validation failed');
});

it('validates week is positive integer', function () {
    expect(fn () => $this->tool->execute([
        'league_id' => 'league_123',
        'week' => 0,
    ]))->toThrow(JsonRpcErrorException::class, 'Validation failed');

    expect(fn () => $this->tool->execute([
        'league_id' => 'league_123',
        'week' => 19,
    ]))->toThrow(JsonRpcErrorException::class, 'Validation failed');
});

it('handles API failure gracefully', function () {
    Http::fake([
        '*' => Http::response(null, 500),
    ]);

    expect(fn () => $this->tool->execute([
        'league_id' => 'league_123',
    ]))->toThrow(JsonRpcErrorException::class);
});

it('fetches matchups for specific week successfully', function () {
    // Mock successful API responses
    Http::fake([
        'https://api.sleeper.app/v1/league/league_123/matchups/5' => Http::response([
            [
                'roster_id' => 1,
                'matchup_id' => 1,
                'points' => 123.45,
                'players' => ['12345', '67890'],
                'starters' => ['12345'],
            ],
            [
                'roster_id' => 2,
                'matchup_id' => 1,
                'points' => 98.76,
                'players' => ['54321', '09876'],
                'starters' => ['54321'],
            ],
        ]),
        '*' => Http::response([]), // Catch-all for any other requests
    ]);

    $result = $this->tool->execute([
        'league_id' => 'league_123',
        'week' => 5,
    ]);

    expect($result)->toBeArray();
    expect($result['success'])->toBeTrue();
    expect($result['data']['matchups'])->toHaveCount(2);
    expect($result['data']['week'])->toBe(5);
    expect($result['data']['league_id'])->toBe('league_123');
    expect($result['data']['filtered_by_user'])->toBeFalse();
    expect($result['count'])->toBe(2);

    // Check basic matchup structure
    $firstMatchup = $result['data']['matchups'][0];
    expect($firstMatchup['roster_id'])->toBe(1);
    expect($firstMatchup['points'])->toBe(123.45);
});

it('fetches matchups for current week when no week specified', function () {
    Http::fake([
        'https://api.sleeper.app/v1/state/*' => Http::response([
            'week' => 8,
            'season_type' => 'regular',
        ]),
        'https://api.sleeper.app/v1/league/league_123/matchups/8' => Http::response([
            [
                'roster_id' => 1,
                'matchup_id' => 1,
                'points' => 150.25,
            ],
        ]),
        'https://api.sleeper.app/v1/league/league_123/rosters' => Http::response([
            ['roster_id' => 1, 'owner_id' => 'user_123'],
        ]),
        'https://api.sleeper.app/v1/league/league_123/users' => Http::response([
            ['user_id' => 'user_123', 'username' => 'testuser'],
        ]),
    ]);

    $result = $this->tool->execute([
        'league_id' => 'league_123',
    ]);

    expect($result['data']['week'])->toBe(8);
    expect($result['metadata']['week'])->toBe(8);
});

it('filters matchups by user_id successfully', function () {
    Http::fake([
        'https://api.sleeper.app/v1/league/league_123/matchups/1' => Http::response([
            [
                'roster_id' => 1,
                'matchup_id' => 1,
                'points' => 120.00,
            ],
            [
                'roster_id' => 2,
                'matchup_id' => 1,
                'points' => 110.00,
            ],
            [
                'roster_id' => 3,
                'matchup_id' => 2,
                'points' => 105.00,
            ],
        ]),
        'https://api.sleeper.app/v1/league/league_123/rosters' => Http::response([
            ['roster_id' => 1, 'owner_id' => 'user_123'],
            ['roster_id' => 2, 'owner_id' => 'user_456'],
            ['roster_id' => 3, 'owner_id' => 'user_789'],
        ]),
        'https://api.sleeper.app/v1/league/league_123/users' => Http::response([
            ['user_id' => 'user_123', 'username' => 'testuser1'],
        ]),
    ]);

    $result = $this->tool->execute([
        'league_id' => 'league_123',
        'week' => 1,
        'user_id' => 'user_123',
    ]);

    expect($result['data']['filtered_by_user'])->toBeTrue();
    expect($result['data']['user_filter']['user_id'])->toBe('user_123');
    expect($result['data']['matchups'])->toHaveCount(1);
    expect($result['data']['matchups'][0]['roster_id'])->toBe(1);
    expect($result['metadata']['total_matchups_in_week'])->toBe(3);
    expect($result['metadata']['filtered_matchups'])->toBe(1);
});

it('filters matchups by username successfully', function () {
    Http::fake([
        'https://api.sleeper.app/v1/user/testuser1' => Http::response([
            'user_id' => 'user_123',
            'username' => 'testuser1',
        ]),
        'https://api.sleeper.app/v1/league/league_123/matchups/1' => Http::response([
            ['roster_id' => 1, 'matchup_id' => 1, 'points' => 120.00],
            ['roster_id' => 2, 'matchup_id' => 1, 'points' => 110.00],
        ]),
        'https://api.sleeper.app/v1/league/league_123/rosters' => Http::response([
            ['roster_id' => 1, 'owner_id' => 'user_123'],
            ['roster_id' => 2, 'owner_id' => 'user_456'],
        ]),
        'https://api.sleeper.app/v1/league/league_123/users' => Http::response([
            ['user_id' => 'user_123', 'username' => 'testuser1'],
        ]),
    ]);

    $result = $this->tool->execute([
        'league_id' => 'league_123',
        'week' => 1,
        'username' => 'testuser1',
    ]);

    expect($result['data']['filtered_by_user'])->toBeTrue();
    expect($result['data']['user_filter']['username'])->toBe('testuser1');
    expect($result['data']['user_filter']['user_id'])->toBe('user_123');
    expect($result['data']['matchups'])->toHaveCount(1);
});

it('returns empty array when user not found in league', function () {
    Http::fake([
        'https://api.sleeper.app/v1/league/league_123/matchups/1' => Http::response([
            ['roster_id' => 1, 'matchup_id' => 1],
        ]),
        'https://api.sleeper.app/v1/league/league_123/rosters' => Http::response([
            ['roster_id' => 1, 'owner_id' => 'different_user'],
        ]),
        'https://api.sleeper.app/v1/league/league_123/users' => Http::response([]),
    ]);

    $result = $this->tool->execute([
        'league_id' => 'league_123',
        'week' => 1,
        'user_id' => 'nonexistent_user',
    ]);

    expect($result['data']['filtered_by_user'])->toBeTrue();
    expect($result['data']['matchups'])->toHaveCount(0);
    expect($result['metadata']['total_matchups_in_week'])->toBe(1);
    expect($result['metadata']['filtered_matchups'])->toBe(0);
});

it('handles username resolution failure gracefully', function () {
    Http::fake([
        'https://api.sleeper.app/v1/user/badusername' => Http::response('', 404),
        'https://api.sleeper.app/v1/league/league_123/matchups/1' => Http::response([]),
        'https://api.sleeper.app/v1/league/league_123/rosters' => Http::response([]),
        'https://api.sleeper.app/v1/league/league_123/users' => Http::response([]),
    ]);

    $result = $this->tool->execute([
        'league_id' => 'league_123',
        'week' => 1,
        'username' => 'badusername',
    ]);

    expect($result['data']['filtered_by_user'])->toBeFalse(); // Should fall back to not filtering
    expect($result['data']['user_filter'])->toBeNull();
});

it('handles current week API failure gracefully', function () {
    Http::fake([
        'https://api.sleeper.app/v1/state/*' => Http::response('', 500),
        'https://api.sleeper.app/v1/league/league_123/matchups/1' => Http::response([]),
        'https://api.sleeper.app/v1/league/league_123/rosters' => Http::response([]),
        'https://api.sleeper.app/v1/league/league_123/users' => Http::response([]),
    ]);

    $result = $this->tool->execute([
        'league_id' => 'league_123',
    ]);

    expect($result['data']['week'])->toBe(1); // Should default to week 1
});

it('handles enhancement failure gracefully', function () {
    Http::fake([
        'https://api.sleeper.app/v1/league/league_123/matchups/1' => Http::response([
            ['roster_id' => 1, 'matchup_id' => 1, 'points' => 100.0],
        ]),
        'https://api.sleeper.app/v1/league/league_123/rosters' => Http::response('', 500),
        'https://api.sleeper.app/v1/league/league_123/users' => Http::response('', 500),
    ]);

    $result = $this->tool->execute([
        'league_id' => 'league_123',
        'week' => 1,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['data']['matchups'])->toHaveCount(1);
    // Should return original matchup data when enhancement fails
    expect($result['data']['matchups'][0])->toHaveKey('roster_id');
    expect($result['data']['matchups'][0])->toHaveKey('matchup_id');
});

it('has correct input schema', function () {
    $schema = $this->tool->inputSchema();

    expect($schema['type'])->toBe('object');
    expect($schema['required'])->toEqual(['league_id']);
    expect($schema['properties'])->toHaveKeys(['league_id', 'week', 'user_id', 'username', 'sport']);
    expect($schema['properties']['league_id']['type'])->toBe('string');

    // Check the new anyOf format for nullable fields
    expect($schema['properties']['week'])->toHaveKey('anyOf');
    expect($schema['properties']['week']['anyOf'])->toHaveCount(2);
    expect($schema['properties']['week']['anyOf'][0])->toEqual(['type' => 'integer', 'minimum' => 1, 'maximum' => 18]);
    expect($schema['properties']['week']['anyOf'][1])->toEqual(['type' => 'null']);

    expect($schema['properties']['user_id'])->toHaveKey('anyOf');
    expect($schema['properties']['user_id']['anyOf'])->toHaveCount(2);
    expect($schema['properties']['user_id']['anyOf'][0])->toEqual(['type' => 'string']);
    expect($schema['properties']['user_id']['anyOf'][1])->toEqual(['type' => 'null']);

    expect($schema['properties']['username'])->toHaveKey('anyOf');
    expect($schema['properties']['username']['anyOf'])->toHaveCount(2);
    expect($schema['properties']['username']['anyOf'][0])->toEqual(['type' => 'string']);
    expect($schema['properties']['username']['anyOf'][1])->toEqual(['type' => 'null']);

    expect($schema['properties']['sport']['type'])->toBe('string');
});

it('has correct annotations', function () {
    $annotations = $this->tool->annotations();

    expect($annotations['title'])->toBe('Fetch League Matchups');
    expect($annotations['readOnlyHint'])->toBeTrue();
    expect($annotations['destructiveHint'])->toBeFalse();
    expect($annotations['idempotentHint'])->toBeTrue();
    expect($annotations['openWorldHint'])->toBeTrue();
    expect($annotations['category'])->toBe('fantasy-sports');
    expect($annotations['data_source'])->toBe('external_api');
    expect($annotations['cache_recommended'])->toBeTrue();
});

it('returns correct metadata structure', function () {
    Http::fake([
        'https://api.sleeper.app/v1/league/league_123/matchups/3' => Http::response([]),
        'https://api.sleeper.app/v1/league/league_123/rosters' => Http::response([]),
        'https://api.sleeper.app/v1/league/league_123/users' => Http::response([]),
    ]);

    $result = $this->tool->execute([
        'league_id' => 'league_123',
        'week' => 3,
        'sport' => 'nfl',
    ]);

    expect($result['metadata'])->toHaveKeys([
        'league_id', 'week', 'sport', 'total_matchups_in_week', 'filtered_matchups', 'executed_at',
    ]);
    expect($result['metadata']['league_id'])->toBe('league_123');
    expect($result['metadata']['week'])->toBe(3);
    expect($result['metadata']['sport'])->toBe('nfl');
});

it('enhances matchups with player data from database', function () {
    // Create test players in the database
    $starterPlayer = Player::factory()->create([
        'player_id' => '12345',
        'first_name' => 'Josh',
        'last_name' => 'Allen',
        'position' => 'QB',
        'team' => 'BUF',
        'fantasy_positions' => ['QB'],
    ]);

    $benchPlayer = Player::factory()->create([
        'player_id' => '67890',
        'first_name' => 'Stefon',
        'last_name' => 'Diggs',
        'position' => 'WR',
        'team' => 'HOU',
        'fantasy_positions' => ['WR'],
    ]);

    // Mock successful API responses with player IDs that match our test players
    Http::fake([
        'https://api.sleeper.app/v1/league/league_123/matchups/5' => Http::response([
            [
                'roster_id' => 1,
                'matchup_id' => 1,
                'points' => 123.45,
                'players' => ['12345', '67890'],
                'starters' => ['12345'],
            ],
        ]),
        'https://api.sleeper.app/v1/league/league_123/rosters' => Http::response([
            ['roster_id' => 1, 'owner_id' => 'user_123'],
        ]),
        'https://api.sleeper.app/v1/league/league_123/users' => Http::response([
            ['user_id' => 'user_123', 'username' => 'testuser', 'display_name' => 'Test User'],
        ]),
    ]);

    $result = $this->tool->execute([
        'league_id' => 'league_123',
        'week' => 5,
    ]);

    expect($result)->toBeArray();
    expect($result['success'])->toBeTrue();
    expect($result['data']['matchups'])->toHaveCount(1);

    $matchup = $result['data']['matchups'][0];

    // Verify original data is preserved
    expect($matchup['roster_id'])->toBe(1);
    expect($matchup['points'])->toBe(123.45);
    expect($matchup['starters'])->toEqual(['12345']);
    expect($matchup['players'])->toEqual(['12345', '67890']);

    // Verify enhanced starters data
    expect($matchup)->toHaveKey('starters_data');
    expect($matchup['starters_data'])->toHaveCount(1);
    expect($matchup['starters_data'][0]['player_id'])->toBe('12345');
    expect($matchup['starters_data'][0]['first_name'])->toBe('Josh');
    expect($matchup['starters_data'][0]['last_name'])->toBe('Allen');
    expect($matchup['starters_data'][0]['position'])->toBe('QB');
    expect($matchup['starters_data'][0]['team'])->toBe('BUF');

    // Verify enhanced players data
    expect($matchup)->toHaveKey('players_data');
    expect($matchup['players_data'])->toHaveCount(2);

    // Check first player (starter)
    $starterData = collect($matchup['players_data'])->firstWhere('player_id', '12345');
    expect($starterData['first_name'])->toBe('Josh');
    expect($starterData['last_name'])->toBe('Allen');
    expect($starterData['position'])->toBe('QB');

    // Check second player (bench)
    $benchData = collect($matchup['players_data'])->firstWhere('player_id', '67890');
    expect($benchData['first_name'])->toBe('Stefon');
    expect($benchData['last_name'])->toBe('Diggs');
    expect($benchData['position'])->toBe('WR');
});

it('handles missing player data gracefully', function () {
    // Mock API response with player IDs that don't exist in the database
    Http::fake([
        'https://api.sleeper.app/v1/league/league_123/matchups/5' => Http::response([
            [
                'roster_id' => 1,
                'matchup_id' => 1,
                'points' => 123.45,
                'players' => ['99999'],
                'starters' => ['99999'],
            ],
        ]),
        'https://api.sleeper.app/v1/league/league_123/rosters' => Http::response([
            ['roster_id' => 1, 'owner_id' => 'user_123'],
        ]),
        'https://api.sleeper.app/v1/league/league_123/users' => Http::response([
            ['user_id' => 'user_123', 'username' => 'testuser'],
        ]),
    ]);

    $result = $this->tool->execute([
        'league_id' => 'league_123',
        'week' => 5,
    ]);

    expect($result['success'])->toBeTrue();
    $matchup = $result['data']['matchups'][0];

    // Verify that missing players return just the player_id
    expect($matchup['starters_data'])->toHaveCount(1);
    expect($matchup['starters_data'][0])->toEqual(['player_id' => '99999']);

    expect($matchup['players_data'])->toHaveCount(1);
    expect($matchup['players_data'][0])->toEqual(['player_id' => '99999']);
});
