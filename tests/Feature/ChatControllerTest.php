<?php

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders chat index page', function () {
    $this->get(route('chat.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Chats'));
});

it('prevents guests from protected chat routes', function () {
    $chat = Chat::factory()->create();

    $this->post(route('chat.store'))->assertRedirect(route('login'));
    $this->get(route('chat.show', $chat))->assertRedirect(route('login'));
    $this->patch(route('chat.update', $chat))->assertRedirect(route('login'));
    $this->delete(route('chat.destroy', $chat))->assertRedirect(route('login'));
});

it('shows a chat to its owner with messages', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create();
    Message::factory()->for($chat)->count(2)->create();

    $this->actingAs($user)
        ->get(route('chat.show', $chat))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Chat')
            ->has('chat', fn (Assert $chatPage) => $chatPage
                ->where('id', $chat->id)
                ->has('messages', 2)
                ->etc()
            )
        );
});

it('forbids viewing another users chat', function () {
    [$owner, $other] = User::factory()->count(2)->create();
    $chat = Chat::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('chat.show', $chat))
        ->assertForbidden();
});

it('creates chat with default untitled when no title provided', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('chat.store'));

    $chat = Chat::first();
    expect($chat)->not->toBeNull();
    expect($chat->title)->toBe('Untitled');

    $response->assertRedirect(route('chat.show', $chat));
});

it('creates chat, stores first message, and flags stream', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('chat.store'), [
        'firstMessage' => 'Hello world',
    ]);

    $chat = Chat::first();
    expect($chat)->not->toBeNull();
    $this->assertDatabaseHas('messages', [
        'chat_id' => $chat->id,
        'type' => 'prompt',
        'content' => 'Hello world',
    ]);

    $response
        ->assertRedirect(route('chat.show', $chat))
        ->assertSessionHas('stream', true);
});

it('updates a chat title', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['title' => 'Old Title']);

    $this->actingAs($user)
        ->patch(route('chat.update', $chat), ['title' => 'New Title'])
        ->assertRedirect();

    $this->assertDatabaseHas('chats', ['id' => $chat->id, 'title' => 'New Title']);
});

it('validates title is required when updating', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('chat.show', $chat))
        ->patch(route('chat.update', $chat), [])
        ->assertSessionHasErrors('title');
});

it('deletes a chat and redirects to home if viewing that chat', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('chat.show', $chat))
        ->delete(route('chat.destroy', $chat))
        ->assertRedirect(route('home'));

    $this->assertDatabaseMissing('chats', ['id' => $chat->id]);
});

it('deletes a chat from sidebar and redirects back', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('chat.index'))
        ->delete(route('chat.destroy', $chat))
        ->assertRedirect(route('chat.index'));
});

it('forbids updating and deleting another users chat', function () {
    [$owner, $other] = User::factory()->count(2)->create();
    $chat = Chat::factory()->for($owner)->create();

    $this->actingAs($other)->patch(route('chat.update', $chat), ['title' => 'X'])->assertForbidden();
    $this->actingAs($other)->delete(route('chat.destroy', $chat))->assertForbidden();
});
