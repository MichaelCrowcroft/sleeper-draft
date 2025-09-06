<?php

use App\Models\Player;
use App\Models\User;
use Livewire\Volt\Volt;

it('can render', function () {
    $component = Volt::test('dashboard');

    $component
        ->assertSee('Dashboard')
        ->assertSee('Welcome to your fantasy football command center')
        ->assertSee('System Overview')
        ->assertSee('Quick Actions');
});

it('displays player statistics', function () {
    // Create some test players
    Player::factory()->create(['active' => true, 'position' => 'QB']);
    Player::factory()->create(['active' => true, 'position' => 'RB']);
    Player::factory()->create(['active' => false]); // inactive player

    $component = Volt::test('dashboard');

    $component
        ->assertSee('Active Players')
        ->assertSee('2') // Should show 2 active players
        ->assertSee('System Overview');
});

it('displays welcome message when authenticated', function () {
    $user = User::factory()->create(['name' => 'John Doe']);

    $component = Volt::actingAs($user)
        ->test('dashboard');

    $component->assertSee('Welcome back, John Doe');
});

it('shows trending players when available', function () {
    // Create players with trending data
    Player::factory()->create([
        'active' => true,
        'first_name' => 'Trending',
        'last_name' => 'Player',
        'position' => 'RB',
        'team' => 'KC',
        'adds_24h' => 150,
    ]);

    Player::factory()->create([
        'active' => true,
        'first_name' => 'Dropping',
        'last_name' => 'Player',
        'position' => 'WR',
        'team' => 'DAL',
        'drops_24h' => 200,
    ]);

    $component = Volt::test('dashboard');

    $component
        ->assertSee('Trending Activity')
        ->assertSee('Top Trending Adds')
        ->assertSee('Top Trending Drops')
        ->assertSee('Trending Player')
        ->assertSee('Dropping Player');
});
