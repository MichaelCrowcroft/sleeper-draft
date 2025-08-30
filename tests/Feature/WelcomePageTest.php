<?php

declare(strict_types=1);

it('renders the redesigned welcome page', function () {
    $response = $this->get('/');

    $response->assertSuccessful()
        ->assertSee('MCP Tools for Fantasy Football')
        ->assertSee('Quickstart — Claude and Cursor')
        ->assertSee('Tools Reference')
        ->assertSee('fantasy_recommendations');
});
