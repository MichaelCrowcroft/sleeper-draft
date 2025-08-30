<?php

namespace App\MCP\Tools;

use App\Models\Player;
use Illuminate\Support\Facades\Validator;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchADPPlayersTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-adp-players';
    }

    public function description(): string
    {
        return 'Fetches top players by Average Draft Position (ADP) from the database. Returns players ordered by ADP in ascending order (lowest ADP first).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'position' => [
                    'type' => 'string',
                    'description' => 'Optional filter to get players for a specific position (e.g., "QB", "RB", "WR", "TE"). Leave empty to get all positions.',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * Provides metadata about the tool's behavior and characteristics.
     *
     * @return array Associative array of tool metadata and behavioral hints
     */
    public function annotations(): array
    {
        return [
            'title' => 'Fetch ADP Players',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false, // Only interacts with local database

            // Custom annotations
            'category' => 'fantasy-sports',
            'data_source' => 'database',
            'cache_recommended' => true,
        ];
    }

    /**
     * The core logic of this tool.
     *
     * Fetches top players by ADP from the database, optionally filtered by position,
     * and returns them ordered by ADP in ascending order.
     *
     * @param  array  $arguments  Associative array of input parameters from the client
     * @return mixed The tool's result (will be JSON-encoded in the response)
     *
     * @throws JsonRpcErrorException When validation fails or execution errors occur
     */
    public function execute(array $arguments): mixed
    {
        // Validate input arguments
        $validator = Validator::make($arguments, [
            'position' => ['nullable', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            throw new JsonRpcErrorException(
                message: 'Validation failed: '.$validator->errors()->first(),
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $position = $arguments['position'] ?? null;

        try {
            // Build query for players with ADP values
            $query = Player::whereNotNull('adp')
                ->orderBy('adp', 'asc'); // Lowest ADP first (most desirable)

            // Apply position filter if provided
            if ($position) {
                $query->where('position', $position);
            }

            $players = $query->get();

            $filterDescription = $position ? " for position '{$position}'" : ' for all positions';

            return [
                'success' => true,
                'data' => $players->toArray(),
                'count' => $players->count(),
                'message' => "Successfully fetched {$players->count()} players by ADP{$filterDescription}",
                'metadata' => [
                    'position_filter' => $position,
                    'order_by' => 'adp',
                    'order_direction' => 'asc',
                    'executed_at' => now()->toISOString(),
                ],
            ];

        } catch (\Exception $e) {
            // Log the error for debugging
            logger('FetchADPPlayersTool execution failed', [
                'tool' => static::class,
                'arguments' => $arguments,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Handle any execution errors gracefully
            throw new JsonRpcErrorException(
                message: 'Tool execution failed: '.$e->getMessage(),
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }
    }
}
