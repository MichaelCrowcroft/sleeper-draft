<?php

use App\Rules\ValidateSleeperUsername;
use Illuminate\Support\Facades\Validator;
use MichaelCrowcroft\SleeperLaravel\Requests\Users\GetUser;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

test('validates existing sleeper username', function () {
    MockClient::global([
        GetUser::class => MockResponse::make([
            'user_id' => '12345678',
            'username' => 'testuser',
            'display_name' => 'Test User',
        ], 200),
    ]);

    $rule = new ValidateSleeperUsername();
    $validator = Validator::make(
        ['sleeper_username' => 'testuser'],
        ['sleeper_username' => $rule]
    );

    expect($validator->passes())->toBeTrue();
    expect($rule->getUserData())->toBe([
        'user_id' => '12345678',
        'username' => 'testuser',
    ]);
});

test('fails validation for non-existent sleeper username', function () {
    MockClient::global([
        GetUser::class => MockResponse::make([], 404),
    ]);

    $rule = new ValidateSleeperUsername();
    $validator = Validator::make(
        ['sleeper_username' => 'nonexistentuser'],
        ['sleeper_username' => $rule]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('sleeper_username'))
        ->toBe('This Sleeper username does not exist. Please check your username and try again.');
});

test('handles api network errors gracefully', function () {
    MockClient::global([
        GetUser::class => function () {
            throw new \Exception('Network error');
        },
    ]);

    $rule = new ValidateSleeperUsername();
    $validator = Validator::make(
        ['sleeper_username' => 'testuser'],
        ['sleeper_username' => $rule]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('sleeper_username'))
        ->toBe('This Sleeper username does not exist. Please check your username and try again.');
});
