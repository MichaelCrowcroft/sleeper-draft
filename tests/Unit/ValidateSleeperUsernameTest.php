<?php

use App\Actions\ValidateSleeperUsername;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MichaelCrowcroft\SleeperLaravel\Requests\Users\GetUser;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

test('validates existing sleeper username', function () {
    MockClient::global([
        GetUser::class => MockResponse::make([
            'user_id' => '12345678',
            'username' => 'testuser',
            'display_name' => 'Test User',
        ], 200),
    ]);

    $action = new ValidateSleeperUsername;
    $result = $action->execute('testuser');

    expect($result)->toBe([
        'user_id' => '12345678',
        'username' => 'testuser',
    ]);
});

test('returns null for non-existent sleeper username', function () {
    MockClient::global([
        GetUser::class => MockResponse::make([], 404),
    ]);

    $action = new ValidateSleeperUsername;
    $result = $action->execute('nonexistentuser');

    expect($result)->toBeNull();
});
