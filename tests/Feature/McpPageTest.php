<?php

declare(strict_types=1);

it('renders the MCP documentation page', function () {
    $response = $this->get('/mcp');

    $response->assertSuccessful()
        ->assertSee('Fantasy Football MCP')
        ->assertSeeText('Claude & Cursor Setup')
        ->assertSee('API Reference')
        ->assertSee('Endpoints');
});
