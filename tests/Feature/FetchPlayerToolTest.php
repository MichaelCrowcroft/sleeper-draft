<?php

use App\MCP\Tools\FetchPlayerTool;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tool = new FetchPlayerTool;
});

it('has correct tool properties', function () {
    expect($this->tool->name())->toBe('fetch-player');
    expect($this->tool->description())->toContain('comprehensive player data');
    expect($this->tool->isStreaming())->toBeFalse();
});

it('has valid input schema', function () {
    $schema = $this->tool->inputSchema();

    expect($schema)->toHaveKey('type', 'object');
    expect($schema)->toHaveKey('properties');
    expect($schema['properties'])->toHaveKey('player_id');
    expect($schema['properties'])->toHaveKey('search');
    expect($schema)->toHaveKey('oneOf');
});

it('has annotations', function () {
    $annotations = $this->tool->annotations();

    expect($annotations)->toHaveKey('title', 'Fetch Player Details');
    expect($annotations)->toHaveKey('category', 'fantasy-sports');
    expect($annotations)->toHaveKey('data_source', 'database');
});

it('returns error when player not found by id', function () {
    $result = $this->tool->execute(['player_id' => 'nonexistent']);

    expect($result)->toHaveKey('success', false);
    expect($result)->toHaveKey('error', 'Player not found');
    expect($result['message'])->toContain('No player found with ID');
});

it('returns error when player not found by search', function () {
    $result = $this->tool->execute(['search' => 'nonexistentplayername']);

    expect($result)->toHaveKey('success', false);
    expect($result)->toHaveKey('error', 'Player not found');
    expect($result['message'])->toContain('No player found matching search');
});

it('returns player data when found by id', function () {
    $player = Player::factory()->create([
        'first_name' => 'Test',
        'last_name' => 'Player',
        'position' => 'QB',
        'fantasy_positions' => ['QB'],
        'team' => 'TB',
        'active' => true,
    ]);

    $result = $this->tool->execute(['player_id' => $player->player_id]);

    expect($result)->toHaveKey('success', true);
    expect($result)->toHaveKey('data');
    expect($result['data'])->toHaveKey('basic_info');
    expect($result['data']['basic_info']['player_id'])->toBe($player->player_id);
    expect($result['data']['basic_info']['first_name'])->toBe('Test');
    expect($result['data']['basic_info']['last_name'])->toBe('Player');
    expect($result['data']['basic_info']['position'])->toBe('QB');
    expect($result['data']['basic_info']['team'])->toBe('TB');
});

it('returns player data when found by search', function () {
    $player = Player::factory()->create([
        'first_name' => 'Christian',
        'last_name' => 'McCaffrey',
        'position' => 'RB',
        'fantasy_positions' => ['RB'],
        'team' => 'SF',
        'active' => true,
    ]);

    $result = $this->tool->execute(['search' => 'McCaffrey']);

    expect($result)->toHaveKey('success', true);
    expect($result)->toHaveKey('data');
    expect($result['data']['basic_info']['last_name'])->toBe('McCaffrey');
    expect($result['data']['basic_info']['first_name'])->toBe('Christian');
    expect($result['data']['basic_info']['team'])->toBe('SF');
});

it('includes all required data sections', function () {
    $player = Player::factory()->create([
        'first_name' => 'Josh',
        'last_name' => 'Allen',
        'position' => 'QB',
        'fantasy_positions' => ['QB'],
        'team' => 'BUF',
        'active' => true,
    ]);

    $result = $this->tool->execute(['player_id' => $player->player_id]);

    expect($result['data'])->toHaveKey('basic_info');
    expect($result['data'])->toHaveKey('season_2024');
    expect($result['data'])->toHaveKey('season_2025');
    expect($result['data'])->toHaveKey('charts');
    expect($result['data'])->toHaveKey('position_stats');
    expect($result['data'])->toHaveKey('raw_data');

    // Check season 2024 structure
    expect($result['data']['season_2024'])->toHaveKey('stats');
    expect($result['data']['season_2024'])->toHaveKey('summary');
    expect($result['data']['season_2024'])->toHaveKey('weekly_points');
    expect($result['data']['season_2024'])->toHaveKey('median_ppg');

    // Check season 2025 structure
    expect($result['data']['season_2025'])->toHaveKey('projections');
    expect($result['data']['season_2025'])->toHaveKey('projection_distribution');
    expect($result['data']['season_2025'])->toHaveKey('weekly_projections');

    // Check charts structure
    expect($result['data']['charts'])->toHaveKey('box_plot');
    expect($result['data']['charts'])->toHaveKey('box_2024_horizontal');

    // Check raw data structure
    expect($result['data']['raw_data'])->toHaveKey('weekly_stats');
    expect($result['data']['raw_data'])->toHaveKey('weekly_projections');
});
