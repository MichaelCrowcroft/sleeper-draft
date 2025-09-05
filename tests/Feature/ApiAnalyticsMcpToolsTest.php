<?php

declare(strict_types=1);

use App\Models\ApiAnalytics;
use Illuminate\Support\Str;

it('logs tool_name for API MCP tool endpoint', function () {
    ApiAnalytics::query()->delete();

    $payload = [
        'position' => 'QB',
    ];

    $this->postJson('/api/mcp/tools/fetch-adp-players', $payload)->assertSuccessful();

    $record = ApiAnalytics::query()
        ->where('endpoint', 'api/mcp/tools/fetch-adp-players')
        ->latest('id')
        ->first();

    expect($record)->not->toBeNull();
    expect($record->tool_name)->toBe('mcp_fantasy-football-mcp_fetch-adp-players');
    expect($record->endpoint_category)->toBe('mcp_tools_api');
});

it('logs tool_name for direct MCP JSON-RPC call', function () {
    ApiAnalytics::query()->delete();

    $jsonRpc = [
        'jsonrpc' => '2.0',
        'id' => (string) Str::uuid(),
        'method' => 'tools/call',
        'params' => [
            'name' => 'fetch-adp-players',
            'arguments' => ['position' => 'QB'],
            'session' => (string) Str::uuid(),
        ],
    ];

    $this->postJson('/mcp', $jsonRpc)->assertSuccessful();

    $record = ApiAnalytics::query()
        ->where('endpoint', 'mcp')
        ->latest('id')
        ->first();

    expect($record)->not->toBeNull();
    expect($record->tool_name)->toBe('mcp_fantasy-football-mcp_fetch-adp-players');
    expect($record->endpoint_category)->toBe('mcp');
});
