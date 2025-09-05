<?php

use App\Http\Controllers\McpActionController;
use Illuminate\Support\Facades\Route;

// MCP Actions Shim Routes
// Individual tool endpoints for better GPT Actions compatibility
// Each route maps to an MCP tool for seamless OpenAI GPT Actions integration

// Specific tool endpoints (recommended for GPT Actions)
Route::middleware('api.analytics')->group(function () {
    Route::post('/mcp/tools/fetch-trending-players', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-trending-players');
    Route::post('/mcp/tools/fetch-adp-players', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-adp-players');
    Route::post('/mcp/tools/fetch-user-leagues', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-user-leagues');
    Route::post('/mcp/tools/draft-picks', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.draft-picks');
    Route::post('/mcp/tools/fetch-league', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-league');
    Route::post('/mcp/tools/fetch-roster', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-roster');
    Route::post('/mcp/tools/fetch-transactions', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-transactions');
    Route::post('/mcp/tools/fetch-matchups', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-matchups');
    Route::post('/mcp/tools/fetch-players-season-data', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-players-season-data');
    Route::post('/mcp/tools/fetch-player-season-data', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-player-season-data');
    Route::post('/mcp/tools/fetch-free-agents-players-season-data', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-free-agents-players-season-data');

    // OpenAPI endpoint
    Route::get('/openapi.yaml', function () {
        $path = public_path('openapi.yaml');

        if (! file_exists($path)) {
            return response()->json([
                'error' => 'OpenAPI specification not found',
                'message' => 'The OpenAPI specification file is not available',
            ], 404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/yaml',
            'Content-Disposition' => 'inline; filename="openapi.yaml"',
        ]);
    })->name('api.openapi');

    // MCP tools listing endpoint
    Route::get('/mcp/tools', function () {
        $tools = [
            'fetch-trending-players',
            'fetch-adp-players',
            'fetch-user-leagues',
            'draft-picks',
            'fetch-league',
            'fetch-roster',
            'fetch-transactions',
            'fetch-matchups',
            'fetch-players-season-data',
            'fetch-player-season-data',
            'fetch-free-agents-players-season-data',
        ];

        return response()->json([
            'tools' => array_map(function ($tool) {
                return [
                    'name' => $tool,
                    'description' => match ($tool) {
                        'fetch-trending-players' => 'Fetches trending players from the database based on adds or drops within the last 24 hours',
                        'fetch-adp-players' => 'Fetches top players by Average Draft Position (ADP) from the database',
                        'fetch-user-leagues' => 'Fetches all leagues for a given Sleeper user',
                        'draft-picks' => 'Provides intelligent draft pick suggestions by analyzing draft state and player ADP values',
                        'fetch-league' => 'Get a specific league and users with team summaries',
                        'fetch-roster' => 'Get a specific user roster enriched with player and owner details',
                        'fetch-transactions' => 'Fetches league transactions with expanded player details',
                        'fetch-matchups' => 'Fetches matchups and scores for a league in a specific week',
                        'fetch-players-season-data' => 'Returns last season stats + summary and current season projections + summary for players',
                        'fetch-player-season-data' => 'Returns last season stats + summary and current season projections + summary for a player by id or name (all matches if multiple)',
                        'fetch-free-agents-players-season-data' => 'Returns players like fetch-players-season-data but excludes those rostered in the specified league',
                    },
                    'endpoint' => "/api/mcp/tools/{$tool}",
                    'method' => 'POST',
                    'route_name' => "api.mcp.{$tool}",
                ];
            }, $tools),
            'openapi_spec_url' => route('api.openapi'),
            'version' => '1.0.0',
            'documentation' => 'See OpenAPI spec for detailed parameter schemas and examples',
        ]);
    })->name('api.mcp.tools');

    // Health check endpoint
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
        ]);
    })->name('api.health');
});
