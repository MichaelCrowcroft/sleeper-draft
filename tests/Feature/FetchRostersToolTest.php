<?php

declare(strict_types=1);

use App\MCP\Tools\FetchRostersTool;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tool = new FetchRostersTool;
});

test('it returns correct tool metadata', function () {
    expect($this->tool->name())->toBe('fetch-rosters');
    expect($this->tool->description())->toContain('Fetches rosters for a league using the Sleeper SDK');
    expect($this->tool->isStreaming())->toBeFalse();
});

test('it has correct input schema', function () {
    $schema = $this->tool->inputSchema();

    expect($schema['type'])->toBe('object');
    expect($schema['properties'])->toHaveKey('league_id');
    expect($schema['properties'])->toHaveKey('include_player_details');
    expect($schema['properties'])->toHaveKey('include_owner_details');
    expect($schema['required'])->toContain('league_id');
});

test('it has correct annotations', function () {
    $annotations = $this->tool->annotations();

    expect($annotations['title'])->toBe('Fetch League Rosters');
    expect($annotations['readOnlyHint'])->toBeTrue();
    expect($annotations['destructiveHint'])->toBeFalse();
    expect($annotations['idempotentHint'])->toBeTrue();
    expect($annotations['openWorldHint'])->toBeTrue();
    expect($annotations['category'])->toBe('fantasy-sports');
});

test('it validates required league_id parameter', function () {
    expect(fn () => $this->tool->execute([]))
        ->toThrow('Validation failed: The league id field is required.');
});

test('it validates league_id must be a string', function () {
    expect(fn () => $this->tool->execute(['league_id' => 123]))
        ->toThrow('Validation failed: The league id field must be a string.');
});

test('it validates boolean parameters', function () {
    expect(fn () => $this->tool->execute([
        'league_id' => 'test-league',
        'include_player_details' => 'invalid',
    ]))->toThrow('Validation failed');

    expect(fn () => $this->tool->execute([
        'league_id' => 'test-league',
        'include_owner_details' => 'invalid',
    ]))->toThrow('Validation failed');
});

test('it accepts valid parameters', function () {
    // This test will fail with network error, but proves validation passes
    try {
        $this->tool->execute([
            'league_id' => 'test-league',
            'include_player_details' => true,
            'include_owner_details' => false,
        ]);
    } catch (\Exception $e) {
        // We expect this to fail with network/API error, not validation error
        expect($e->getMessage())->not()->toContain('Validation failed');
    }
});

test('enhance player array works correctly', function () {
    // Create test players in database
    Player::factory()->create([
        'player_id' => 'player1',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'position' => 'QB',
    ]);

    Player::factory()->create([
        'player_id' => 'player2',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'position' => 'RB',
    ]);

    // Use reflection to test private method
    $tool = new FetchRostersTool;
    $reflector = new ReflectionClass($tool);
    $method = $reflector->getMethod('enhancePlayerArray');
    $method->setAccessible(true);

    $playersFromDb = Player::all()
        ->mapWithKeys(fn($player) => [
            $player->player_id => (new \App\Http\Resources\PlayerResource($player))->resolve(),
        ])
        ->toArray();
    $playerIds = ['player1', 'player2', 'player3']; // player3 not in DB

    $result = $method->invoke($tool, $playerIds, $playersFromDb);

    expect($result)->toHaveCount(3);
    expect($result[0]['player_id'])->toBe('player1');
    expect($result[0]['player_data'])->not()->toBeNull();
    expect($result[0]['player_data']['first_name'])->toBe('John');

    expect($result[1]['player_id'])->toBe('player2');
    expect($result[1]['player_data'])->not()->toBeNull();
    expect($result[1]['player_data']['first_name'])->toBe('Jane');

    expect($result[2]['player_id'])->toBe('player3');
    expect($result[2]['player_data'])->toBeNull();
});
