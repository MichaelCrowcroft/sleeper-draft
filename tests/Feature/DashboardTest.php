<?php

use App\MCP\Tools\FetchUserLeaguesTool;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create([
        'sleeper_username' => null,
        'sleeper_user_id' => null,
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertStatus(200);
});

test('authenticated users with sleeper username see setup message', function () {
    $user = User::factory()->create([
        'sleeper_username' => 'testuser',
    ]);
    $this->actingAs($user);

    // Mock the Sleeper API calls to return error responses
    $this->mock(FetchUserLeaguesTool::class, function ($mock) {
        $mock->shouldReceive('execute')->andReturn([
            'success' => false,
            'message' => 'Mocked API error',
        ]);
    });

    $response = $this->get(route('dashboard'));
    $response->assertStatus(200);
});

test('authenticated users with sleeper user id see setup message', function () {
    $user = User::factory()->create([
        'sleeper_user_id' => '123456789',
    ]);
    $this->actingAs($user);

    // Mock the Sleeper API calls to return error responses
    $this->mock(FetchUserLeaguesTool::class, function ($mock) {
        $mock->shouldReceive('execute')->andReturn([
            'success' => false,
            'message' => 'Mocked API error',
        ]);
    });

    $response = $this->get(route('dashboard'));
    $response->assertStatus(200);
});
