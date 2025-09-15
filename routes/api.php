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
    Route::post('/mcp/tools/fetch-players', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-players');
    Route::post('/mcp/tools/fetch-player', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-player');
    Route::post('/mcp/tools/evaluate-trade', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.evaluate-trade');
    Route::post('/mcp/tools/fetch-matchups', [McpActionController::class, 'invokeTool'])
        ->name('api.mcp.fetch-matchups');
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
            'fetch-players',
            'fetch-player',
            'evaluate-trade',
            'fetch-matchups',
            'fetch-user-leagues',
        ];

        return response()->json([
            'tools' => array_map(function ($tool) {
                return [
                    'name' => $tool,
                    'description' => match ($tool) {
                        'fetch-trending-players' => 'Fetches trending players from the database based on adds or drops within the last 24 hours. Returns players ordered by trending value in descending order.',
                        'fetch-players' => 'Fetch a paginated, enriched list of players with comprehensive filtering and league integration. Pass a league ID and you can see free agents and rostered player owner information.',
                        'fetch-player' => 'Fetch comprehensive player data including stats, projections, volatility metrics, and performance analysis. Accepts either a player ID or search term to find a player.',
                        'evaluate-trade' => 'Evaluate a fantasy football trade by analyzing player stats, projections, and providing aggregate comparison between receiving and sending sides.',
                        'fetch-matchups' => 'Fetch enriched matchups for a league and week, including player data, projections, win probabilities, and confidence intervals. If user_id is provided, returns only matchups for that user; otherwise returns all matchups in the league.',
                        'fetch-user-leagues' => 'Fetches all leagues for a user by username or user ID. Returns league IDs and names for the specified sport and season.',
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
