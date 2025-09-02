<?php

declare(strict_types=1);

it('renders the MCP documentation page', function () {
    $response = $this->get('/mcp');

    $response->assertSuccessful()
        ->assertSee('MCP Tools for Fantasy Football')
        ->assertSee('Quickstart — Claude and Cursor')
        ->assertSee('Tools Reference')
        ->assertSee('fantasy_recommendations')
        ->assertSee('Endpoints');
});
