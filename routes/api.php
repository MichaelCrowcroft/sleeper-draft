<?php

use App\Http\Controllers\McpActionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// MCP Actions Shim Routes
// Each route maps to an MCP tool for seamless OpenAI GPT Actions integration
// Apply rate limiting and security headers to prevent abuse
Route::post('/mcp/tools/fetch-trending-players', [McpActionController::class, 'invoke'])
    ->name('api.mcp.fetch-trending-players');

Route::post('/mcp/tools/fetch-adp-players', [McpActionController::class, 'invoke'])
    ->name('api.mcp.fetch-adp-players');

Route::post('/mcp/tools/fetch-user-leagues', [McpActionController::class, 'invoke'])
    ->name('api.mcp.fetch-user-leagues');

Route::post('/mcp/tools/draft-picks', [McpActionController::class, 'invoke'])
    ->name('api.mcp.draft-picks');

Route::post('/mcp/tools/get-league', [McpActionController::class, 'invoke'])
    ->name('api.mcp.get-league');

Route::post('/mcp/tools/fetch-rosters', [McpActionController::class, 'invoke'])
    ->name('api.mcp.fetch-rosters');

Route::post('/mcp/tools/get-matchups', [McpActionController::class, 'invoke'])
    ->name('api.mcp.get-matchups');

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
    return response()->json([
        'tools' => [
            [
                'name' => 'fetch-trending-players',
                'description' => 'Fetches trending players from the database based on adds or drops within the last 24 hours',
                'endpoint' => '/api/mcp/tools/fetch-trending-players',
                'method' => 'POST',
            ],
            [
                'name' => 'fetch-adp-players',
                'description' => 'Fetches top players by Average Draft Position (ADP) from the database',
                'endpoint' => '/api/mcp/tools/fetch-adp-players',
                'method' => 'POST',
            ],
            [
                'name' => 'fetch-user-leagues',
                'description' => 'Fetches all leagues for a given Sleeper user',
                'endpoint' => '/api/mcp/tools/fetch-user-leagues',
                'method' => 'POST',
            ],
            [
                'name' => 'draft-picks',
                'description' => 'Provides intelligent draft pick suggestions by analyzing draft state and player ADP values',
                'endpoint' => '/api/mcp/tools/draft-picks',
                'method' => 'POST',
            ],
            [
                'name' => 'get-league',
                'description' => 'Get league information and users for a specific league',
                'endpoint' => '/api/mcp/tools/get-league',
                'method' => 'POST',
            ],
            [
                'name' => 'fetch-rosters',
                'description' => 'Fetches rosters for all users in a league',
                'endpoint' => '/api/mcp/tools/fetch-rosters',
                'method' => 'POST',
            ],
            [
                'name' => 'get-matchups',
                'description' => 'Fetches matchups and scores for a league in a specific week',
                'endpoint' => '/api/mcp/tools/get-matchups',
                'method' => 'POST',
            ],
        ],
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
