<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('can display the api tokens page', function () {
    $response = $this->actingAs($this->user)
        ->get(route('api-tokens.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('settings/ApiTokens')
        ->has('tokens')
    );
});

it('can create a new api token', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('api-tokens.store'), [
            'name' => 'Test Token',
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'token' => [
            'id',
            'name',
            'token',
            'created_at',
            'abilities',
        ],
    ]);

    // Verify token was created in database
    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $this->user->id,
        'name' => 'MCP: Test Token',
    ]);
});

it('validates token name is required', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('api-tokens.store'), [
            'name' => '',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

it('prevents duplicate token names', function () {
    // Create first token
    $this->user->createToken('MCP: Test Token');

    // Try to create another token with same name
    $response = $this->actingAs($this->user)
        ->postJson(route('api-tokens.store'), [
            'name' => 'Test Token',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

it('limits the number of tokens per user', function () {
    // Create 10 tokens (max allowed)
    for ($i = 1; $i <= 10; $i++) {
        $this->user->createToken("MCP: Test Token {$i}");
    }

    // Try to create an 11th token
    $response = $this->actingAs($this->user)
        ->postJson(route('api-tokens.store'), [
            'name' => 'Too Many Tokens',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

it('can revoke a specific token', function () {
    $token = $this->user->createToken('MCP: Test Token');

    $response = $this->actingAs($this->user)
        ->deleteJson(route('api-tokens.destroy', $token->accessToken->id));

    $response->assertSuccessful();

    // Verify token was deleted
    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->accessToken->id,
    ]);
});

it('cannot revoke tokens that do not belong to the user', function () {
    $otherUser = User::factory()->create();
    $token = $otherUser->createToken('MCP: Other User Token');

    $response = $this->actingAs($this->user)
        ->deleteJson(route('api-tokens.destroy', $token->accessToken->id));

    $response->assertNotFound();
});

it('can revoke all tokens', function () {
    // Create multiple tokens
    $this->user->createToken('MCP: Token 1');
    $this->user->createToken('MCP: Token 2');
    $this->user->createToken('MCP: Token 3');

    $response = $this->actingAs($this->user)
        ->deleteJson(route('api-tokens.destroy-all'));

    $response->assertSuccessful();

    // Verify all tokens were deleted
    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $this->user->id,
    ]);
});

it('only affects mcp tokens when revoking', function () {
    // Create MCP token and regular token
    $mcpToken = $this->user->createToken('MCP: Test Token');
    $regularToken = $this->user->createToken('Regular Token');

    // Revoke all MCP tokens
    $response = $this->actingAs($this->user)
        ->deleteJson(route('api-tokens.destroy-all'));

    $response->assertSuccessful();

    // Verify only MCP token was deleted
    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $mcpToken->accessToken->id,
    ]);

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $regularToken->accessToken->id,
    ]);
});

it('can authenticate with sanctum token', function () {
    $token = $this->user->createToken('MCP: Test Token', ['mcp:access']);

    $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'id' => 1,
            'params' => [],
        ]);

    // Should not get 401 unauthorized (would happen without token)
    // Since we have valid authentication, any status except 401 is acceptable
    expect($response->status())->not->toBe(401);
});
