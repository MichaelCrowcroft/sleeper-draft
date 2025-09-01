<?php

use App\Http\Controllers\McpActionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// MCP Actions Shim Routes
// Individual tool endpoints for better GPT Actions compatibility
// Each route maps to an MCP tool for seamless OpenAI GPT Actions integration

// Specific tool endpoints (recommended for GPT Actions)
Route::post('/mcp/tools/fetch-trending-players', [McpActionController::class, 'invokeTool'])
    ->name('api.mcp.fetch-trending-players');
Route::post('/mcp/tools/fetch-adp-players', [McpActionController::class, 'invokeTool'])
    ->name('api.mcp.fetch-adp-players');
Route::post('/mcp/tools/fetch-user-leagues', [McpActionController::class, 'invokeTool'])
    ->name('api.mcp.fetch-user-leagues');
Route::post('/mcp/tools/draft-picks', [McpActionController::class, 'invokeTool'])
    ->name('api.mcp.draft-picks');
Route::post('/mcp/tools/get-league', [McpActionController::class, 'invokeTool'])
    ->name('api.mcp.get-league');
Route::post('/mcp/tools/fetch-rosters', [McpActionController::class, 'invokeTool'])
    ->name('api.mcp.fetch-rosters');
Route::post('/mcp/tools/fetch-matchups', [McpActionController::class, 'invokeTool'])
    ->name('api.mcp.fetch-matchups');
Route::post('/mcp/tools/fetch-trades', [McpActionController::class, 'invokeTool'])
    ->name('api.mcp.fetch-trades');

// Legacy generic endpoint for backward compatibility
Route::post('/mcp/tools/{tool}', [McpActionController::class, 'invoke'])
    ->where('tool', 'fetch-trending-players|fetch-adp-players|fetch-user-leagues|draft-picks|get-league|fetch-rosters|fetch-matchups|fetch-trades')
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
        'fetch-matchups',
        'fetch-trades',
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
                    'fetch-matchups' => 'Fetches matchups and scores for a league in a specific week',
                    'fetch-trades' => 'Fetches trades in a league with expanded player details',
                },
                'endpoint' => "/api/mcp/tools/{$tool}",
                'method' => 'POST',
                'route_name' => "api.mcp.{$tool}",
            ];
        }, $tools),
        'openapi_spec_url' => route('api.openapi'),
        'version' => '1.0.0',
        'documentation' => 'See OpenAPI spec for detailed parameter schemas and examples',
        'note' => 'Both individual endpoints (/api/mcp/tools/{tool}) and legacy generic endpoint (/api/mcp/tools/{tool}) are supported',
    ]);
})->name('api.mcp.tools');

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
    ]);
})->name('api.health');
