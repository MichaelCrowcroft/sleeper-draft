<?php

declare(strict_types=1);

it('renders the simplified welcome page', function () {
    $response = $this->get('/');

    $response->assertSuccessful()
        ->assertSee('MCP Tools for Fantasy Football')
        ->assertSee('Connect your AI assistant to Sleeper fantasy football data')
        ->assertSee('Try the Custom GPT')
        ->assertSee('View MCP Documentation')
        ->assertSee('How to Use');

    $response->assertSee('<title>MCP Tools for Fantasy Football - AI-Powered Draft Assistant</title>', false);
});
