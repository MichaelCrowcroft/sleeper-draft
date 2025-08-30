<?php

declare(strict_types=1);

use App\MCP\Tools\DraftPicksTool;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tool = new DraftPicksTool;
});

it('has correct tool properties', function () {
    expect($this->tool->name())->toBe('draft-picks');
    expect($this->tool->isStreaming())->toBeFalse();
    expect($this->tool->description())->toContain('intelligent draft pick suggestions');
});

it('validates required fields', function () {
    expect(fn () => $this->tool->execute([]))
        ->toThrow(JsonRpcErrorException::class, 'Validation failed');
});

it('validates user_id is required', function () {
    expect(fn () => $this->tool->execute(['draft_id' => 'test']))
        ->toThrow(JsonRpcErrorException::class, 'Validation failed');
});

it('validates draft_id is required', function () {
    expect(fn () => $this->tool->execute(['user_id' => 'test']))
        ->toThrow(JsonRpcErrorException::class, 'Validation failed');
});

it('validates limit parameter', function () {
    expect(fn () => $this->tool->execute([
        'user_id' => 'test_user',
        'draft_id' => 'test_draft',
        'limit' => 0,
    ]))->toThrow(JsonRpcErrorException::class, 'Validation failed');

    expect(fn () => $this->tool->execute([
        'user_id' => 'test_user',
        'draft_id' => 'test_draft',
        'limit' => 100,
    ]))->toThrow(JsonRpcErrorException::class, 'Validation failed');
});

it('handles API failure gracefully', function () {
    Http::fake([
        'api.sleeper.app/v1/draft/*' => Http::response(null, 500),
    ]);

    expect(fn () => $this->tool->execute([
        'user_id' => 'test_user',
        'draft_id' => 'test_draft',
    ]))->toThrow(JsonRpcErrorException::class, 'Failed to fetch draft data');
});

it('handles successful API response structure', function () {
    // Mock successful API responses
    Http::fake([
        'api.sleeper.app/v1/draft/test_draft' => Http::response([
            'type' => 'snake',
            'status' => 'in_progress',
            'scoring_type' => 'std',
            'season_type' => 'regular',
            'draft_order' => [
                'test_user' => 1,
                'other_user' => 2,
            ],
            'settings' => [
                'teams' => 2, // Number of teams, not array of teams
            ],
        ]),
        'api.sleeper.app/v1/draft/test_draft/picks' => Http::response([
            [
                'pick_no' => 1,
                'roster_id' => 1,
                'player_id' => 'player1',
            ],
        ]),
    ]);

    // Create some test players
    Player::factory()->create([
        'player_id' => 'available_player_1',
        'position' => 'RB',
        'adp' => 10,
        'full_name' => 'Test Player 1',
    ]);

    Player::factory()->create([
        'player_id' => 'available_player_2',
        'position' => 'WR',
        'adp' => 15,
        'full_name' => 'Test Player 2',
    ]);

    $result = $this->tool->execute([
        'user_id' => 'test_user',
        'draft_id' => 'test_draft',
        'limit' => 5,
    ]);

    expect($result)->toBeArray();
    expect($result['success'])->toBeTrue();
    expect($result['data'])->toHaveKeys(['suggestions', 'draft_analysis', 'roster_composition', 'positional_needs']);
    expect($result['metadata'])->toHaveKeys(['user_id', 'draft_id', 'draft_type', 'scoring_type', 'season_type']);
});

it('handles user not found in draft', function () {
    Http::fake([
        'api.sleeper.app/v1/draft/test_draft' => Http::response([
            'type' => 'snake',
            'draft_order' => [
                '1' => 'user1',
            ],
            'settings' => [
                'teams' => [
                    [
                        'roster_id' => 1,
                        'user_id' => 'different_user',
                    ],
                ],
            ],
        ]),
        'api.sleeper.app/v1/draft/test_draft/picks' => Http::response([]),
    ]);

    expect(fn () => $this->tool->execute([
        'user_id' => 'test_user',
        'draft_id' => 'test_draft',
    ]))->toThrow(JsonRpcErrorException::class, 'User not found in this draft');
});

it('has correct input schema', function () {
    $schema = $this->tool->inputSchema();

    expect($schema['type'])->toBe('object');
    expect($schema['required'])->toEqual(['user_id', 'draft_id']);
    expect($schema['properties'])->toHaveKeys(['user_id', 'draft_id', 'limit']);
    expect($schema['properties']['user_id']['type'])->toBe('string');
    expect($schema['properties']['draft_id']['type'])->toBe('string');
    expect($schema['properties']['limit']['type'])->toBe('integer');
});

it('has correct annotations', function () {
    $annotations = $this->tool->annotations();

    expect($annotations['title'])->toBe('Draft Picks Recommendations');
    expect($annotations['readOnlyHint'])->toBeTrue();
    expect($annotations['destructiveHint'])->toBeFalse();
    expect($annotations['idempotentHint'])->toBeTrue();
    expect($annotations['openWorldHint'])->toBeTrue();
    expect($annotations['category'])->toBe('fantasy-sports');
    expect($annotations['data_source'])->toBe('external_api');
    expect($annotations['cache_recommended'])->toBeFalse();
});
