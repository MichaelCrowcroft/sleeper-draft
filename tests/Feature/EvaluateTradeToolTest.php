<?php

use App\MCP\Tools\EvaluateTradeTool;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tool = new EvaluateTradeTool;
});

it('has correct tool properties', function () {
    expect($this->tool->name())->toBe('evaluate-trade');
    expect($this->tool->description())->toContain('fantasy football trade');
    expect($this->tool->isStreaming())->toBeFalse();
});

it('has valid input schema', function () {
    $schema = $this->tool->inputSchema();

    expect($schema)->toHaveKey('type', 'object');
    expect($schema)->toHaveKey('properties');
    expect($schema['properties'])->toHaveKey('receiving');
    expect($schema['properties'])->toHaveKey('sending');
    expect($schema)->toHaveKey('required');
    expect($schema['required'])->toContain('receiving');
    expect($schema['required'])->toContain('sending');
});

it('has annotations', function () {
    $annotations = $this->tool->annotations();

    expect($annotations)->toHaveKey('title', 'Evaluate Fantasy Trade');
    expect($annotations)->toHaveKey('category', 'fantasy-sports');
    expect($annotations)->toHaveKey('data_source', 'database');
});

it('returns error when receiving players not found', function () {
    $result = $this->tool->execute([
        'receiving' => [['player_id' => 'nonexistent']],
        'sending' => [],
    ]);

    expect($result)->toHaveKey('success', false);
    expect($result)->toHaveKey('error', 'Some players not found');
    expect($result)->toHaveKey('missing_receiving');
    expect($result['missing_receiving'])->toHaveCount(1);
});

it('returns error when sending players not found', function () {
    $result = $this->tool->execute([
        'receiving' => [],
        'sending' => [['search' => 'nonexistentplayer']],
    ]);

    expect($result)->toHaveKey('success', false);
    expect($result)->toHaveKey('error', 'Some players not found');
    expect($result)->toHaveKey('missing_sending');
    expect($result['missing_sending'])->toHaveCount(1);
});

it('successfully evaluates trade between two players', function () {
    $receivingPlayer = Player::factory()->create([
        'first_name' => 'Christian',
        'last_name' => 'McCaffrey',
        'position' => 'RB',
        'team' => 'SF',
    ]);

    $sendingPlayer = Player::factory()->create([
        'first_name' => 'Josh',
        'last_name' => 'Allen',
        'position' => 'QB',
        'team' => 'BUF',
    ]);

    $result = $this->tool->execute([
        'receiving' => [['player_id' => $receivingPlayer->player_id]],
        'sending' => [['player_id' => $sendingPlayer->player_id]],
    ]);

    expect($result)->toHaveKey('success', true);
    expect($result)->toHaveKey('data');
    expect($result['data'])->toHaveKey('receiving');
    expect($result['data'])->toHaveKey('sending');
    expect($result['data'])->toHaveKey('analysis');

    // Check receiving side
    expect($result['data']['receiving'])->toHaveKey('players');
    expect($result['data']['receiving'])->toHaveKey('aggregates');
    expect($result['data']['receiving']['players'])->toHaveCount(1);
    expect($result['data']['receiving']['players'][0]['basic_info']['first_name'])->toBe('Christian');
    expect($result['data']['receiving']['players'][0]['basic_info']['last_name'])->toBe('McCaffrey');

    // Check sending side
    expect($result['data']['sending'])->toHaveKey('players');
    expect($result['data']['sending'])->toHaveKey('aggregates');
    expect($result['data']['sending']['players'])->toHaveCount(1);
    expect($result['data']['sending']['players'][0]['basic_info']['first_name'])->toBe('Josh');
    expect($result['data']['sending']['players'][0]['basic_info']['last_name'])->toBe('Allen');

    // Check aggregates
    expect($result['data']['receiving']['aggregates'])->toHaveKey('total_players', 1);
    expect($result['data']['sending']['aggregates'])->toHaveKey('total_players', 1);

    // Check analysis
    expect($result['data']['analysis'])->toHaveKey('value_differential');
    expect($result['data']['analysis'])->toHaveKey('recommendation');
    expect($result['data']['analysis'])->toHaveKey('key_insights');
});

it('handles multiple players on each side', function () {
    $receivingPlayers = Player::factory()->count(2)->create([
        'position' => 'RB',
    ]);

    $sendingPlayers = Player::factory()->count(3)->create([
        'position' => 'QB',
    ]);

    $result = $this->tool->execute([
        'receiving' => $receivingPlayers->map(fn($p) => ['player_id' => $p->player_id])->toArray(),
        'sending' => $sendingPlayers->map(fn($p) => ['player_id' => $p->player_id])->toArray(),
    ]);

    expect($result)->toHaveKey('success', true);
    expect($result['data']['receiving']['players'])->toHaveCount(2);
    expect($result['data']['sending']['players'])->toHaveCount(3);
    expect($result['data']['receiving']['aggregates']['total_players'])->toBe(2);
    expect($result['data']['sending']['aggregates']['total_players'])->toBe(3);
});

it('calculates position breakdown correctly', function () {
    $qbPlayer = Player::factory()->create([
        'position' => 'QB',
        'fantasy_positions' => ['QB'],
        'first_name' => 'QB',
        'last_name' => 'Player'
    ]);
    $rbPlayer = Player::factory()->create([
        'position' => 'RB',
        'fantasy_positions' => ['RB'],
        'first_name' => 'RB',
        'last_name' => 'Player'
    ]);
    $wrPlayer = Player::factory()->create([
        'position' => 'WR',
        'fantasy_positions' => ['WR'],
        'first_name' => 'WR',
        'last_name' => 'Player'
    ]);

    $result = $this->tool->execute([
        'receiving' => [
            ['player_id' => $qbPlayer->player_id],
            ['player_id' => $rbPlayer->player_id],
            ['player_id' => $wrPlayer->player_id],
        ],
        'sending' => [],
    ]);

    expect($result)->toHaveKey('success', true);
    $positionBreakdown = $result['data']['receiving']['aggregates']['position_breakdown'];

    // Check what positions are actually being returned
    expect(count($positionBreakdown))->toBe(3);
    expect($positionBreakdown)->toHaveKey('QB');
    expect($positionBreakdown)->toHaveKey('RB');
    expect($positionBreakdown)->toHaveKey('WR');
    expect($positionBreakdown['QB'])->toBe(1);
    expect($positionBreakdown['RB'])->toBe(1);
    expect($positionBreakdown['WR'])->toBe(1);
});

it('provides trade recommendation', function () {
    $highValuePlayer = Player::factory()->create([
        'first_name' => 'High',
        'last_name' => 'Value',
        'position' => 'QB',
    ]);

    $lowValuePlayer = Player::factory()->create([
        'first_name' => 'Low',
        'last_name' => 'Value',
        'position' => 'QB',
    ]);

    $result = $this->tool->execute([
        'receiving' => [['player_id' => $highValuePlayer->player_id]],
        'sending' => [['player_id' => $lowValuePlayer->player_id]],
    ]);

    expect($result)->toHaveKey('success', true);
    expect($result['data']['analysis'])->toHaveKey('recommendation');
    expect($result['data']['analysis']['recommendation'])->toHaveKey('action');
    expect($result['data']['analysis']['recommendation'])->toHaveKey('confidence');
    expect($result['data']['analysis']['recommendation'])->toHaveKey('reason');
});

it('includes comprehensive metadata', function () {
    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();

    $result = $this->tool->execute([
        'receiving' => [['player_id' => $player1->player_id]],
        'sending' => [['player_id' => $player2->player_id]],
    ]);

    expect($result)->toHaveKey('success', true);
    expect($result)->toHaveKey('metadata');
    expect($result['metadata'])->toHaveKey('receiving_count', 1);
    expect($result['metadata'])->toHaveKey('sending_count', 1);
    expect($result['metadata'])->toHaveKey('evaluated_at');
    expect($result['metadata'])->toHaveKey('data_sources');
    expect($result['metadata']['data_sources'])->toHaveKey('player_table', true);
    expect($result['metadata']['data_sources'])->toHaveKey('stats_2024', true);
    expect($result['metadata']['data_sources'])->toHaveKey('projections_2025', true);
});

it('handles empty receiving and sending arrays', function () {
    $result = $this->tool->execute([
        'receiving' => [],
        'sending' => [],
    ]);

    expect($result)->toHaveKey('success', true);
    expect($result['data']['receiving']['players'])->toHaveCount(0);
    expect($result['data']['sending']['players'])->toHaveCount(0);
    expect($result['data']['receiving']['aggregates']['total_players'])->toBe(0);
    expect($result['data']['sending']['aggregates']['total_players'])->toBe(0);
});

it('works with search terms instead of player IDs', function () {
    $player1 = Player::factory()->create([
        'first_name' => 'Patrick',
        'last_name' => 'Mahomes',
    ]);

    $player2 = Player::factory()->create([
        'first_name' => 'Lamar',
        'last_name' => 'Jackson',
    ]);

    $result = $this->tool->execute([
        'receiving' => [['search' => 'Mahomes']],
        'sending' => [['search' => 'Jackson']],
    ]);

    expect($result)->toHaveKey('success', true);
    expect($result['data']['receiving']['players'][0]['basic_info']['last_name'])->toBe('Mahomes');
    expect($result['data']['sending']['players'][0]['basic_info']['last_name'])->toBe('Jackson');
});
