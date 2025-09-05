<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class McpActionController extends Controller
{
    /**
     * Handle MCP tool invocations from specific tool endpoints
     *
     * This method handles requests from individual tool endpoints like /mcp/tools/fetch-adp-players
     */
    public function invokeTool(Request $request)
    {
        // Extract tool name from the route
        $routeName = $request->route()->getName();

        // Map route names to tool class names (support new and legacy routes)
        $routeToToolClassMap = [
            'api.mcp.fetch-trending-players' => \App\MCP\Tools\FetchTrendingPlayersTool::class,
            'api.mcp.fetch-adp-players' => \App\MCP\Tools\FetchADPPlayersTool::class,
            'api.mcp.fetch-user-leagues' => \App\MCP\Tools\FetchUserLeaguesTool::class,
            'api.mcp.draft-picks' => \App\MCP\Tools\DraftPicksTool::class,
            // New names
            'api.mcp.fetch-league' => \App\MCP\Tools\FetchLeagueTool::class,
            'api.mcp.fetch-roster' => \App\MCP\Tools\FetchRosterTool::class,
            'api.mcp.fetch-transactions' => \App\MCP\Tools\FetchTransactionsTool::class,
            // Unchanged
            'api.mcp.fetch-matchups' => \App\MCP\Tools\FetchMatchupsTool::class,

            'api.mcp.fetch-players-season-data' => \App\MCP\Tools\FetchPlayersSeasonDataTool::class,
            'api.mcp.fetch-player-season-data' => \App\MCP\Tools\FetchPlayerSeasonDataTool::class,
        ];

        $toolClass = $routeToToolClassMap[$routeName] ?? null;

        if (! $toolClass) {
            return response()->json([
                'error' => 'Invalid route',
                'message' => 'Could not determine tool class from route',
            ], 500);
        }

        try {
            // Instantiate and execute the tool directly
            $tool = app($toolClass);
            $result = $tool->execute($request->all());

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('MCP Tool execution failed', [
                'tool' => $toolClass,
                'arguments' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Tool execution failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle MCP tool invocations from OpenAI Custom GPT Actions
     *
     * This method acts as a shim between OpenAI's Actions format and our MCP server.
     * It receives plain JSON requests from GPT Actions and forwards them as MCP tool calls.
     */
    public function invoke(Request $request, string $tool)
    {
        try {
            Log::info('MCP Action invoked', [
                'tool' => $tool,
                'arguments' => $request->all(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);

            // Validate that the tool is available
            $availableTools = $this->getAvailableTools();
            if (! in_array($tool, $availableTools)) {
                Log::warning('Unknown MCP tool requested', ['tool' => $tool]);

                return response()->json([
                    'error' => 'Unknown tool',
                    'message' => "Tool '{$tool}' is not available",
                    'available_tools' => $availableTools,
                ], 404);
            }

            // Build MCP tool call request
            $sessionId = Str::uuid()->toString();
            $mcpRequest = [
                'jsonrpc' => '2.0',
                'id' => Str::uuid()->toString(),
                'method' => 'tools/call',
                'params' => [
                    'name' => $this->mapToolName($tool),
                    'arguments' => $request->all(),
                    'session' => $sessionId,
                ],
            ];

            // Get MCP server configuration
            $mcpUrl = env('APP_URL').'/mcp';
            $timeout = 60;

            // Make request to MCP server
            $httpClient = Http::timeout($timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'Laravel-MCP-Actions-Shim/1.0',
                ]);

            $response = $httpClient->post($mcpUrl, $mcpRequest);

            if ($response->failed()) {
                Log::error('MCP server request failed', [
                    'tool' => $tool,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'error' => 'MCP server error',
                    'message' => 'Failed to execute tool on MCP server',
                    'status_code' => $response->status(),
                ], 502);
            }

            $mcpResponse = $response->json();

            // Handle MCP error responses
            if (isset($mcpResponse['error'])) {
                Log::warning('MCP tool returned error', [
                    'tool' => $tool,
                    'error' => $mcpResponse['error'],
                ]);

                return response()->json([
                    'error' => 'Tool execution failed',
                    'message' => $mcpResponse['error']['message'] ?? 'Unknown error',
                    'mcp_error' => $mcpResponse['error'],
                ], 400);
            }

            // Extract the result from MCP response
            $result = $mcpResponse['result'] ?? null;

            Log::info('MCP Action completed successfully', [
                'tool' => $tool,
                'has_result' => ! is_null($result),
            ]);

            // Return the tool result in a format suitable for GPT Actions
            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('MCP Action exception', [
                'tool' => $tool,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred while processing the request',
            ], 500);
        }
    }

    /**
     * Get list of available MCP tools
     */
    private function getAvailableTools(): array
    {
        return [
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
        ];
    }

    /**
     * Map tool names from Actions format to MCP format if needed
     */
    private function mapToolName(string $tool): string
    {
        $mapping = [
            'fetch-trending-players' => 'fetch-trending-players',
            'fetch-adp-players' => 'fetch-adp-players',
            'fetch-user-leagues' => 'fetch-user-leagues',
            'draft-picks' => 'draft-picks',
            // New names
            'fetch-league' => 'fetch-league',
            'fetch-roster' => 'fetch-roster',
            'fetch-transactions' => 'fetch-transactions',
            'fetch-matchups' => 'fetch-matchups',
            'fetch-players-season-data' => 'fetch-players-season-data',
            'fetch-player-season-data' => 'fetch-player-season-data',
        ];

        return $mapping[$tool] ?? $tool;
    }
}
