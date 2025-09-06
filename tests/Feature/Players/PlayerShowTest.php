<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\User;

it('renders player show snapshot with tabs and bell curve', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Minimal player with identifiers
    $player = Player::factory()->create([
        'active' => true,
        'position' => 'WR',
    ]);

    // Hit page
    $res = $this->get(route('players.show', $player->player_id));

    $res->assertSuccessful();
    // Presence of snapshot container
    $res->assertSee('Performance Snapshot', false);
    // Bell curve SVG path marker
    $res->assertSee('<svg', false)->assertSee('path', false);
    // Tabs
    $res->assertSee('2024 Results', false)
        ->assertSee('2025 Projections', false)
        ->assertSee('Next Game', false);
});
