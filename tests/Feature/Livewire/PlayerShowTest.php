<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\PlayerStats;
use Livewire\Volt\Volt;

test('player show page displays position-specific stats for WR', function () {
    $player = Player::factory()->create([
        'position' => 'WR',
        'fantasy_positions' => ['WR'],
        'first_name' => 'Test',
        'last_name' => 'Player',
    ]);

    // Create some sample stats
    PlayerStats::create([
        'player_id' => $player->player_id,
        'game_date' => '2024-09-01',
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'stats' => [
            'rec' => 8,
            'rec_yd' => 120,
            'rec_td' => 2,
            'rec_tgt' => 10,
            'rec_lng' => 45,
            'rec_ypr' => 15.0,
            'pts_ppr' => 32.0,
        ],
    ]);

    Volt::test('players.show', ['playerId' => $player->player_id])
        ->assertSee('Test Player')
        ->assertSee('WR')
        ->assertSee('2024 Detailed Stats (WR)')
        ->assertSee('Receiving Stats')
        ->assertSee('Receptions:')
        ->assertSee('8')
        ->assertSee('Receiving Yards:')
        ->assertSee('120')
        ->assertSee('Receiving TDs:')
        ->assertSee('2')
        ->assertSee('Targets:')
        ->assertSee('10')
        ->assertSee('Longest Reception:')
        ->assertSee('45 yds');
});

test('player show page displays position-specific stats for RB', function () {
    $player = Player::factory()->create([
        'position' => 'RB',
        'fantasy_positions' => ['RB'],
        'first_name' => 'Running',
        'last_name' => 'Back',
    ]);

    // Create some sample stats
    PlayerStats::create([
        'player_id' => $player->player_id,
        'game_date' => '2024-09-01',
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'stats' => [
            'rush_yd' => 150,
            'rush_td' => 2,
            'rush_att' => 20,
            'rec' => 5,
            'rec_yd' => 40,
            'rec_td' => 1,
            'rec_tgt' => 6,
            'pts_ppr' => 38.0,
        ],
    ]);

    Volt::test('players.show', ['playerId' => $player->player_id])
        ->assertSee('Running Back')
        ->assertSee('RB')
        ->assertSee('2024 Detailed Stats (RB)')
        ->assertSee('Rushing')
        ->assertSee('Rushing Yards:')
        ->assertSee('150')
        ->assertSee('Rushing TDs:')
        ->assertSee('2')
        ->assertSee('Carries:')
        ->assertSee('20')
        ->assertSee('Receiving')
        ->assertSee('Receptions:')
        ->assertSee('5')
        ->assertSee('Receiving Yards:')
        ->assertSee('40');
});

test('player show page displays position-specific stats for TE', function () {
    $player = Player::factory()->create([
        'position' => 'TE',
        'fantasy_positions' => ['TE'],
        'first_name' => 'Tight',
        'last_name' => 'End',
    ]);

    // Create some sample stats
    PlayerStats::create([
        'player_id' => $player->player_id,
        'game_date' => '2024-09-01',
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'stats' => [
            'rec' => 6,
            'rec_yd' => 75,
            'rec_td' => 1,
            'rec_tgt' => 8,
            'rec_lng' => 25,
            'rec_ypr' => 12.5,
            'pts_ppr' => 21.5,
        ],
    ]);

    Volt::test('players.show', ['playerId' => $player->player_id])
        ->assertSee('Tight End')
        ->assertSee('TE')
        ->assertSee('2024 Detailed Stats (TE)')
        ->assertSee('Receiving Stats')
        ->assertSee('Efficiency')
        ->assertSee('6')
        ->assertSee('75')
        ->assertSee('1')
        ->assertSee('8')
        ->assertSee('25 yds')
        ->assertSee('12.5');
});

test('player show page handles unknown position gracefully', function () {
    $player = Player::factory()->create([
        'position' => 'OL',
        'fantasy_positions' => ['OL'],
        'first_name' => 'Offensive',
        'last_name' => 'Lineman',
    ]);

    // Create some sample stats
    PlayerStats::create([
        'player_id' => $player->player_id,
        'game_date' => '2024-09-01',
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'stats' => [
            'some_stat' => 10,
            'another_stat' => 5,
            'pts_ppr' => 0.0,
        ],
    ]);

    Volt::test('players.show', ['playerId' => $player->player_id])
        ->assertSee('Offensive Lineman')
        ->assertSee('OL')
        ->assertSee('2024 Detailed Stats (OL)')
        ->assertSee('Position-specific stats not available for OL position')
        ->assertSee('Available stats:');
});

test('player show page handles player with no stats', function () {
    $player = Player::factory()->create([
        'position' => 'WR',
        'fantasy_positions' => ['WR'],
        'first_name' => 'No',
        'last_name' => 'Stats',
    ]);

    Volt::test('players.show', ['playerId' => $player->player_id])
        ->assertSee('No Stats')
        ->assertSee('WR')
        ->assertSee('2024 Stats')
        ->assertSee('No 2024 stats available for this player');
});
