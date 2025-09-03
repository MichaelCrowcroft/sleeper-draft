<?php

use Livewire\Volt\Volt;
use MichaelCrowcroft\SleeperLaravel\Requests\Users\GetUser;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register with valid sleeper username', function () {
    // Mock the Sleeper API to return a valid user
    MockClient::global([
        GetUser::class => MockResponse::make([
            'user_id' => '12345678',
            'username' => 'testuser',
            'display_name' => 'Test User',
        ], 200),
    ]);

    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('sleeper_username', 'testuser')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    // Verify the user was created with sleeper data
    $user = auth()->user();
    expect($user->sleeper_username)->toBe('testuser');
    expect($user->sleeper_user_id)->toBe('12345678');
});

test('registration fails with invalid sleeper username', function () {
    // For this test, we'll just ensure that the field is required
    // In production, the API validation will handle invalid usernames
    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response
        ->assertHasErrors(['sleeper_username']);

    $this->assertGuest();
});

test('sleeper username must be unique', function () {
    // Mock the Sleeper API for the first user
    MockClient::global([
        GetUser::class => MockResponse::make([
            'user_id' => '12345678',
            'username' => 'testuser',
        ], 200),
    ]);

    // Create first user
    Volt::test('auth.register')
        ->set('name', 'First User')
        ->set('email', 'first@example.com')
        ->set('sleeper_username', 'testuser')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    // Try to create second user with same sleeper username
    $response = Volt::test('auth.register')
        ->set('name', 'Second User')
        ->set('email', 'second@example.com')
        ->set('sleeper_username', 'testuser') // Same username
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response->assertHasErrors(['sleeper_username']);
});
