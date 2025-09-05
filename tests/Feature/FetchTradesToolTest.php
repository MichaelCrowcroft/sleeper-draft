<?php

declare(strict_types=1);

use App\MCP\Tools\FetchTradesTool;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tool = new FetchTradesTool;
});

it('has correct tool properties', function () {
    expect($this->tool->name())->toBe('fetch-trades');
    expect($this->tool->isStreaming())->toBeFalse();
    expect($this->tool->description())->toContain('trades');
});

it('validates required fields', function () {
    expect(fn () => $this->tool->execute([]))
        ->toThrow(JsonRpcErrorException::class, 'Validation failed');
});

it('handles API failure gracefully', function () {
    Http::fake([
        '*' => Http::response(null, 500),
    ]);

    expect(fn () => $this->tool->execute([
        'league_id' => 'league_123',
    ]))->toThrow(JsonRpcErrorException::class);
});

it('returns trades with expanded player details and filters pending when requested', function () {
    Player::factory()->create([
        'player_id' => 'p1',
        'full_name' => 'Player One',
        'position' => 'QB',
        'team' => 'BUF',
    ]);

    Player::factory()->create([
        'player_id' => 'p2',
        'full_name' => 'Player Two',
        'position' => 'WR',
        'team' => 'KC',
    ]);

    Http::fake([
        'https://api.sleeper.app/v1/league/league_123/transactions/1' => Http::response([
            [
                'type' => 'trade',
                'transaction_id' => 'tx1',
                'status' => 'pending',
                'roster_ids' => [1, 2],
                'adds' => [
                    'p1' => 2,
                    'p2' => 1,
                ],
                'drops' => [
                    'p1' => 1,
                    'p2' => 2,
                ],
                'draft_picks' => [],
                'waiver_budget' => [],
            ],
            [
                'type' => 'trade',
                'transaction_id' => 'tx2',
                'status' => 'complete',
                'roster_ids' => [3, 4],
                'adds' => [],
                'drops' => [],
                'draft_picks' => [],
                'waiver_budget' => [],
            ],
        ]),
    ]);

    $result = $this->tool->execute([
        'league_id' => 'league_123',
        'round' => 1,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['count'])->toBe(2);

    $trade = collect($result['data'])->firstWhere('transaction_id', 'tx1');
    expect($trade['adds'])->toHaveCount(2);

    $addP1 = collect($trade['adds'])->firstWhere('player_id', 'p1');
    expect($addP1['player']['full_name'])->toBe('Player One');
    expect($addP1['to_roster_id'])->toBe(2);

    $dropP2 = collect($trade['drops'])->firstWhere('player_id', 'p2');
    expect($dropP2['player']['full_name'])->toBe('Player Two');
    expect($dropP2['from_roster_id'])->toBe(2);

    $pendingResult = $this->tool->execute([
        'league_id' => 'league_123',
        'round' => 1,
        'pending_only' => true,
    ]);

    expect($pendingResult['count'])->toBe(1);
    expect($pendingResult['data'][0]['transaction_id'])->toBe('tx1');
});
