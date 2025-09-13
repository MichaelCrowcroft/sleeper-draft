<?php

use App\MCP\Tools\FetchUserLeaguesTool;

it('returns error when user_identifier is missing', function () {
    $tool = new FetchUserLeaguesTool;
    $result = $tool->execute([]);

    expect($result)->toHaveKey('success', false);
    expect($result)->toHaveKey('error', 'Missing required parameter: user_identifier');
    expect($result['message'])->toContain('user_identifier parameter is required');
});

it('has correct tool properties', function () {
    $tool = new FetchUserLeaguesTool;

    expect($tool->name())->toBe('fetch-user-leagues');
    expect($tool->description())->toContain('leagues for a user');
    expect($tool->isStreaming())->toBeFalse();
});

it('has valid input schema', function () {
    $tool = new FetchUserLeaguesTool;
    $schema = $tool->inputSchema();

    expect($schema)->toHaveKey('type', 'object');
    expect($schema)->toHaveKey('properties');
    expect($schema['properties'])->toHaveKey('user_identifier');
    expect($schema['properties'])->toHaveKey('sport');
    expect($schema['properties'])->toHaveKey('season');
    expect($schema)->toHaveKey('required');
    expect($schema['required'])->toContain('user_identifier');
});
