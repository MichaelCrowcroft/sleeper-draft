<?php

declare(strict_types=1);

use App\MCP\Tools\GetLeagueTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tool = new GetLeagueTool;
});

it('has correct tool properties', function () {
    expect($this->tool->name())->toBe('get-league');
    expect($this->tool->isStreaming())->toBeFalse();
    expect($this->tool->description())->toContain('Get League tool fetches all leagues');
});

it('validates required fields', function () {
    expect(fn () => $this->tool->execute([]))
        ->toThrow(JsonRpcErrorException::class, 'Validation failed');
});

it('validates user_id is required', function () {
    expect(fn () => $this->tool->execute(['league_identifier' => 'test']))
        ->toThrow(JsonRpcErrorException::class, 'Validation failed');
});

it('validates league_identifier is required', function () {
    expect(fn () => $this->tool->execute(['user_id' => 'test']))
        ->toThrow(JsonRpcErrorException::class, 'Validation failed');
});

it('handles API failure gracefully', function () {
    Http::fake([
        '*' => Http::response(null, 500),
    ]);

    expect(fn () => $this->tool->execute([
        'user_id' => 'test_user',
        'league_identifier' => 'test_league',
    ]))->toThrow(JsonRpcErrorException::class);
});

it('finds league by ID successfully', function () {
    // Mock successful API responses
    Http::fake([
        '*/state/*' => Http::response([
            'league_season' => '2024',
            'season' => '2024',
        ]),
        '*/user/test_user/leagues/nfl/2024' => Http::response([
            [
                'league_id' => 'league_123',
                'name' => 'Test League',
                'status' => 'in_season',
                'season' => '2024',
                'settings' => [
                    'teams' => 12,
                ],
            ],
            [
                'league_id' => 'league_456',
                'name' => 'Other League',
                'status' => 'in_season',
                'season' => '2024',
                'settings' => [
                    'teams' => 10,
                ],
            ],
        ]),
        '*/league/league_123' => Http::response([
            'league_id' => 'league_123',
            'name' => 'Test League',
            'status' => 'in_season',
            'season' => '2024',
            'settings' => [
                'teams' => 12,
                'playoff_week_start' => 15,
            ],
        ]),
        '*/league/league_123/users' => Http::response([
            [
                'user_id' => 'test_user',
                'username' => 'testuser',
                'display_name' => 'Test User',
                'avatar' => 'avatar_123',
                'metadata' => [
                    'team_name' => 'Team Test',
                ],
            ],
            [
                'user_id' => 'user_456',
                'username' => 'otheruser',
                'display_name' => 'Other User',
                'avatar' => 'avatar_456',
                'metadata' => [
                    'team_name' => 'Team Other',
                ],
            ],
        ]),
        '*/league/league_123/rosters' => Http::response([
            [
                'roster_id' => 1,
                'owner_id' => 'test_user',
                'settings' => [
                    'wins' => 5,
                    'losses' => 2,
                    'ties' => 0,
                    'fpts' => 1250.5,
                    'fpts_against' => 1100.2,
                ],
            ],
            [
                'roster_id' => 2,
                'owner_id' => 'user_456',
                'settings' => [
                    'wins' => 3,
                    'losses' => 4,
                    'ties' => 0,
                    'fpts' => 1100.0,
                    'fpts_against' => 1200.0,
                ],
            ],
        ]),
    ]);

    $result = $this->tool->execute([
        'user_id' => 'test_user',
        'league_identifier' => 'league_123',
        'sport' => 'nfl',
    ]);

    expect($result)->toBeArray();
    expect($result['success'])->toBeTrue();
    expect($result['data'])->toHaveKeys(['league', 'users']);
    expect($result['data']['league']['league_id'])->toBe('league_123');
    expect($result['data']['league']['name'])->toBe('Test League');
    expect($result['data']['users'])->toHaveCount(2);
    expect($result['data']['users'][0])->toHaveKeys([
        'user_id', 'username', 'display_name', 'team_name', 'roster',
    ]);
    expect($result['data']['users'][0]['team_name'])->toBe('Team Test');
    expect($result['data']['users'][0]['roster']['wins'])->toBe(5);
    expect($result['metadata'])->toHaveKeys([
        'user_id', 'league_id', 'league_name', 'sport', 'season', 'total_users',
    ]);
    expect($result['metadata']['total_users'])->toBe(2);
});

it('finds league by name successfully', function () {
    Http::fake([
        'https://api.sleeper.app/v1/state/*' => Http::response([
            'league_season' => '2024',
        ]),
        '*/user/test_user/leagues/nfl/2024' => Http::response([
            [
                'league_id' => 'league_123',
                'name' => 'My Fantasy League',
                'status' => 'in_season',
                'season' => '2024',
            ],
        ]),
        '*/league/league_123' => Http::response([
            'league_id' => 'league_123',
            'name' => 'My Fantasy League',
            'status' => 'in_season',
        ]),
        '*/league/league_123/users' => Http::response([
            [
                'user_id' => 'test_user',
                'username' => 'testuser',
                'display_name' => 'Test User',
            ],
        ]),
        '*/league/league_123/rosters' => Http::response([]),
    ]);

    $result = $this->tool->execute([
        'user_id' => 'test_user',
        'league_identifier' => 'My Fantasy League',
        'sport' => 'nfl',
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['data']['league']['name'])->toBe('My Fantasy League');
});

it('handles league not found', function () {
    Http::fake([
        'https://api.sleeper.app/v1/state/*' => Http::response([
            'league_season' => '2024',
        ]),
        '*/user/test_user/leagues/nfl/2024' => Http::response([
            [
                'league_id' => 'league_123',
                'name' => 'Different League',
                'status' => 'in_season',
            ],
        ]),
    ]);

    expect(fn () => $this->tool->execute([
        'user_id' => 'test_user',
        'league_identifier' => 'Non-existent League',
    ]))->toThrow(JsonRpcErrorException::class, "League with identifier 'Non-existent League' not found");
});

it('has correct input schema', function () {
    $schema = $this->tool->inputSchema();

    expect($schema['type'])->toBe('object');
    expect($schema['required'])->toEqual(['user_id', 'league_identifier']);
    expect($schema['properties'])->toHaveKeys(['user_id', 'league_identifier', 'sport', 'season']);
    expect($schema['properties']['user_id']['type'])->toBe('string');
    expect($schema['properties']['league_identifier']['type'])->toBe('string');
    expect($schema['properties']['sport']['type'])->toBe('string');
    expect($schema['properties']['season']['type'])->toBe('string');
});

it('has correct annotations', function () {
    $annotations = $this->tool->annotations();

    expect($annotations['title'])->toBe('Get League Information');
    expect($annotations['readOnlyHint'])->toBeTrue();
    expect($annotations['destructiveHint'])->toBeFalse();
    expect($annotations['idempotentHint'])->toBeTrue();
    expect($annotations['openWorldHint'])->toBeTrue();
    expect($annotations['category'])->toBe('fantasy-sports');
    expect($annotations['data_source'])->toBe('external_api');
    expect($annotations['cache_recommended'])->toBeTrue();
});

it('handles users without rosters gracefully', function () {
    Http::fake([
        'https://api.sleeper.app/v1/state/*' => Http::response(['league_season' => '2024']),
        '*/user/test_user/leagues/nfl/2024' => Http::response([
            ['league_id' => 'league_123', 'name' => 'Test League'],
        ]),
        '*/league/league_123' => Http::response([
            'league_id' => 'league_123', 'name' => 'Test League',
        ]),
        '*/league/league_123/users' => Http::response([
            ['user_id' => 'orphan_user', 'username' => 'orphan', 'display_name' => 'Orphaned User'],
        ]),
        '*/league/league_123/rosters' => Http::response([]),
    ]);

    $result = $this->tool->execute([
        'user_id' => 'test_user',
        'league_identifier' => 'league_123',
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['data']['users'])->toHaveCount(1);
    expect($result['data']['users'][0]['roster'])->toBeNull();
    expect($result['data']['users'][0]['team_name'])->toBe('Orphaned User');
});
