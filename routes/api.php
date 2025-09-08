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
    Route::post('/mcp/tools/fetch-players-season-data', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-players-season-data');
    Route::post('/mcp/tools/fetch-player-season-data', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-player-season-data');
    Route::post('/mcp/tools/fetch-league', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-league');
    Route::post('/mcp/tools/fetch-matchups', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-matchups');
    Route::post('/mcp/tools/fetch-roster', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-roster');
    Route::post('/mcp/tools/fetch-user-leagues', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-user-leagues');

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
            'fetch-players-season-data',
            'fetch-player-season-data',
            'fetch-league',
            'fetch-matchups',
            'fetch-roster',
            'fetch-user-leagues',
        ];

        return response()->json([
            'tools' => array_map(function ($tool) {
                return [
                    'name' => $tool,
                    'description' => match ($tool) {
                        'fetch-trending-players' => 'Fetches trending players from the database based on adds or drops within the last 24 hours',
                        'fetch-adp-players' => 'Fetches top players by Average Draft Position (ADP) from the database',
                        'fetch-players-season-data' => 'Returns last season stats + summary and current season projections + summary for players',
                        'fetch-player-season-data' => 'Returns last season stats + summary and current season projections + summary for a player by id or name (all matches if multiple)',
                        'fetch-league' => 'Get League tool fetches all leagues for a user and returns one league based on the name or ID',
                        'fetch-matchups' => 'Fetch matchups for a league and week, returning raw matchup data supplemented with basic user info',
                        'fetch-roster' => 'Gets a specific roster for a user in a league. Returns roster data with player information from the database and owner details',
                        'fetch-user-leagues' => 'Fetches all leagues for a user by username or user ID. Returns league IDs and names for the specified sport and season',
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
