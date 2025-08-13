<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('protects the sleeper settings page', function () {
    $this->get(route('sleeper.edit'))->assertRedirect(route('login'));

    $user = User::factory()->create();
    $this->actingAs($user)->get(route('sleeper.edit'))->assertOk();
});

it('updates sleeper settings and auto-fetches user id', function () {
    $user = User::factory()->create();

    $payload = [
        'sleeper_username' => 'fantasygoat',
    ];

    // Fake the Sleeper SDK via container binding
    $mock = Mockery::mock(\App\Services\SleeperSdk::class);
    $mock->shouldReceive('getUserByUsername')
        ->once()
        ->with('fantasygoat')
        ->andReturn(['user_id' => '1234567890']);
    $this->app->instance(\App\Services\SleeperSdk::class, $mock);

    $this->actingAs($user)
        ->patch(route('sleeper.update'), $payload)
        ->assertRedirect(route('sleeper.edit'));

    $user->refresh();

    expect($user->sleeper_username)->toBe('fantasygoat');
    expect($user->sleeper_user_id)->toBe('1234567890');
});

it('returns validation error if username not found', function () {
    $user = User::factory()->create();

    $mock = Mockery::mock(\App\Services\SleeperSdk::class);
    $mock->shouldReceive('getUserByUsername')
        ->once()
        ->with('missing')
        ->andThrow(new Exception('User not found'));
    $this->app->instance(\App\Services\SleeperSdk::class, $mock);

    $response = $this->actingAs($user)
        ->patch(route('sleeper.update'), ['sleeper_username' => 'missing']);

    $response->assertSessionHasErrors('sleeper_username');
});
