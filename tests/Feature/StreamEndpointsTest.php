<?php

use App\Actions\Chat\StreamChatAction;
use App\Actions\Chat\TitleStreamAction;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires auth to stream on a chat', function () {
    $chat = Chat::factory()->create();
    $this->post(route('chat.show.stream', $chat))->assertRedirect(route('login'));
    $this->get(route('chat.title.stream', $chat))->assertRedirect(route('login'));
});

it('authorizes owner for stream endpoints', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create();

    $this->actingAs($user);

    $mockStream = $this->mock(StreamChatAction::class);
    $mockStream->shouldReceive('__invoke')->once()->andReturn(response('streaming'));

    $this->post(route('chat.show.stream', $chat))->assertOk();

    $mockTitle = $this->mock(TitleStreamAction::class);
    $mockTitle->shouldReceive('__invoke')->once()->andReturn(response('titling'));

    $this->get(route('chat.title.stream', $chat))->assertOk();
});

it('forbids non-owners for stream endpoints', function () {
    [$owner, $other] = User::factory()->count(2)->create();
    $chat = Chat::factory()->for($owner)->create();

    $this->actingAs($other)
        ->post(route('chat.show.stream', $chat))
        ->assertForbidden();

    $this->actingAs($other)
        ->get(route('chat.title.stream', $chat))
        ->assertForbidden();
});
