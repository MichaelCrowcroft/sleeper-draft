<?php

declare(strict_types=1);

use App\MCP\Tools\FetchLeagueTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tool = new FetchLeagueTool;
});

it('has correct tool properties', function () {
    expect($this->tool->name())->toBe('get-league');
    expect($this->tool->isStreaming())->toBeFalse();
    expect($this->tool->description())->toContain('Get League tool fetches all leagues');
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
        'https://api.sleeper.app/v1/state/*' => Http::response([
            'league_season' => '2024',
            'season' => '2024',
        ]),
        'https://api.sleeper.app/v1/user/test_user/leagues/nfl/2024' => Http::response([
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
        'https://api.sleeper.app/v1/league/league_123' => Http::response([
            'league_id' => 'league_123',
            'name' => 'Test League',
            'status' => 'in_season',
            'season' => '2024',
            'settings' => [
                'teams' => 12,
                'playoff_week_start' => 15,
            ],
        ]),
        'https://api.sleeper.app/v1/league/league_123/users' => Http::response([
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
        'https://api.sleeper.app/v1/league/league_123/rosters' => Http::response([
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
    expect($result)->toHaveKeys(['league', 'users']);
    expect($result['league']['league_id'])->toBe('league_123');
    expect($result['league']['name'])->toBe('Test League');
    expect($result['users'])->toHaveCount(2);
    expect($result['users'][0])->toHaveKeys([
        'user_id', 'username', 'display_name', 'team_name', 'wins', 'losses', 'fpts',
    ]);
    expect($result['users'][0]['team_name'])->toBe('Team Test');
    expect($result['users'][0]['wins'])->toBe(5);
});

it('finds league by name successfully', function () {
    Http::fake([
        'https://api.sleeper.app/v1/state/*' => Http::response([
            'league_season' => '2024',
        ]),
        'https://api.sleeper.app/v1/user/test_user/leagues/nfl/2024' => Http::response([
            [
                'league_id' => 'league_123',
                'name' => 'My Fantasy League',
                'status' => 'in_season',
                'season' => '2024',
            ],
        ]),
        'https://api.sleeper.app/v1/league/league_123' => Http::response([
            'league_id' => 'league_123',
            'name' => 'My Fantasy League',
            'status' => 'in_season',
        ]),
        'https://api.sleeper.app/v1/league/league_123/users' => Http::response([
            [
                'user_id' => 'test_user',
                'username' => 'testuser',
                'display_name' => 'Test User',
            ],
        ]),
        'https://api.sleeper.app/v1/league/league_123/rosters' => Http::response([]),
    ]);

    $result = $this->tool->execute([
        'user_id' => 'test_user',
        'league_identifier' => 'My Fantasy League',
        'sport' => 'nfl',
    ]);

    expect($result['league']['name'])->toBe('My Fantasy League');
});

it('handles league not found', function () {
    Http::fake([
        'https://api.sleeper.app/v1/state/*' => Http::response([
            'league_season' => '2024',
        ]),
        'https://api.sleeper.app/v1/user/test_user/leagues/nfl/2024' => Http::response([
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
    ]))->toThrow(JsonRpcErrorException::class, "League 'Non-existent League' not found");
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

it('has simplified annotations', function () {
    $annotations = $this->tool->annotations();
    expect($annotations)->toBe([]);
});

it('handles users without rosters gracefully', function () {
    Http::fake([
        'https://api.sleeper.app/v1/state/*' => Http::response(['league_season' => '2024']),
        'https://api.sleeper.app/v1/user/test_user/leagues/nfl/2024' => Http::response([
            ['league_id' => 'league_123', 'name' => 'Test League'],
        ]),
        'https://api.sleeper.app/v1/league/league_123' => Http::response([
            'league_id' => 'league_123', 'name' => 'Test League',
        ]),
        'https://api.sleeper.app/v1/league/league_123/users' => Http::response([
            ['user_id' => 'orphan_user', 'username' => 'orphan', 'display_name' => 'Orphaned User'],
        ]),
        'https://api.sleeper.app/v1/league/league_123/rosters' => Http::response([]),
    ]);

    $result = $this->tool->execute([
        'user_id' => 'test_user',
        'league_identifier' => 'league_123',
    ]);

    expect($result['users'])->toHaveCount(1);
    expect($result['users'][0]['wins'])->toBe(0);
    expect($result['users'][0]['team_name'])->toBe('Orphaned User');
});
