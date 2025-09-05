<?php

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('players index page renders successfully', function () {
    $user = User::factory()->create([
        'sleeper_username' => null,
        'sleeper_user_id' => null,
    ]);

    $response = $this->actingAs($user)->get(route('players.index'));

    $response->assertStatus(200);
    $response->assertSee('Players');
    $response->assertSee('Browse and filter fantasy football players');
});

test('guests are redirected from players page', function () {
    $response = $this->get(route('players.index'));

    $response->assertRedirect('/login');
});

test('players index page loads without errors when no players exist', function () {
    $user = User::factory()->create([
        'sleeper_username' => null,
        'sleeper_user_id' => null,
    ]);

    $response = $this->actingAs($user)->get(route('players.index'));

    $response->assertStatus(200);
    $response->assertSee('Players');
    $response->assertSee('Browse and filter fantasy football players');
});

test('players index page loads with players data', function () {
    $user = User::factory()->create([
        'sleeper_username' => null,
        'sleeper_user_id' => null,
    ]);

    $player = Player::factory()->create([
        'first_name' => 'Test',
        'last_name' => 'Player',
        'position' => 'QB',
        'team' => 'TB',
        'active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('players.index'));

    $response->assertStatus(200);
    $response->assertSee('Test Player');
    $response->assertSee('QB');
    $response->assertSee('TB');
});

test('players show page renders successfully', function () {
    $user = User::factory()->create([
        'sleeper_username' => null,
        'sleeper_user_id' => null,
    ]);

    $player = Player::factory()->create([
        'first_name' => 'Test',
        'last_name' => 'Player',
        'position' => 'QB',
        'team' => 'TB',
        'active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('players.show', $player->player_id));

    $response->assertStatus(200);
    $response->assertSee('Test Player');
    $response->assertSee('QB');
    $response->assertSee('TB');
});

test('players show page with invalid player ID returns 404', function () {
    $user = User::factory()->create([
        'sleeper_username' => null,
        'sleeper_user_id' => null,
    ]);

    $response = $this->actingAs($user)->get(route('players.show', 'invalid-id'));

    $response->assertStatus(404);
});
