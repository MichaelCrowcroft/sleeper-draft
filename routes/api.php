<?php

use App\Http\Controllers\McpActionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// MCP Actions Shim Routes
// Each route maps to an MCP tool for seamless OpenAI GPT Actions integration
// Apply rate limiting and security headers to prevent abuse
Route::post('/mcp/tools/{tool}', [McpActionController::class, 'invoke'])
    ->where('tool', 'fetch-trending-players|fetch-adp-players|fetch-user-leagues|draft-picks|get-league|fetch-rosters|get-matchups')
    ->name('api.mcp.invoke');

Route::get('/openapi.yaml', function () {
    $path = public_path('openapi.yaml');

    if (!file_exists($path)) {
        return response()->json([
            'error' => 'OpenAPI specification not found',
            'message' => 'The OpenAPI specification file is not available'
        ], 404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/yaml',
        'Content-Disposition' => 'inline; filename="openapi.yaml"'
    ]);
})->name('api.openapi');

Route::get('/mcp/tools', function () {
    $tools = [
        'fetch-trending-players',
        'fetch-adp-players',
        'fetch-user-leagues',
        'draft-picks',
        'get-league',
        'fetch-rosters',
        'get-matchups',
    ];

    return response()->json([
        'tools' => array_map(function ($tool) {
            return [
                'name' => $tool,
                'description' => match($tool) {
                    'fetch-trending-players' => 'Fetches trending players from the database based on adds or drops within the last 24 hours',
                    'fetch-adp-players' => 'Fetches top players by Average Draft Position (ADP) from the database',
                    'fetch-user-leagues' => 'Fetches all leagues for a given Sleeper user',
                    'draft-picks' => 'Provides intelligent draft pick suggestions by analyzing draft state and player ADP values',
                    'get-league' => 'Get league information and users for a specific league',
                    'fetch-rosters' => 'Fetches rosters for all users in a league',
                    'get-matchups' => 'Fetches matchups and scores for a league in a specific week',
                },
                'endpoint' => "/api/mcp/tools/{$tool}",
                'method' => 'POST',
            ];
        }, $tools),
        'openapi_spec_url' => route('api.openapi'),
        'version' => '1.0.0',
        'documentation' => 'See OpenAPI spec for detailed parameter schemas and examples',
    ]);
})->name('api.mcp.tools');

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
    ]);
})->name('api.health');
