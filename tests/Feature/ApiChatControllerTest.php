<?php

use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns empty array for guests', function () {
    $this->getJson(route('api.chats.index'))
        ->assertOk()
        ->assertExactJson([]);
});

it('lists authenticated user chats only', function () {
    [$user, $other] = User::factory()->count(2)->create();
    Chat::factory()->for($user)->count(2)->create();
    Chat::factory()->for($other)->count(3)->create();

    $this->actingAs($user)
        ->getJson(route('api.chats.index'))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJson(fn ($json) => $json
            ->each(fn ($chat) => $chat
                ->hasAll(['id', 'title', 'created_at', 'updated_at'])
            )
        );
});
