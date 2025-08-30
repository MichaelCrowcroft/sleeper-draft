<?php

namespace App\MCP\Tools;

use App\Models\Player;
use Illuminate\Support\Facades\Validator;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchTrendingPlayersTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-trending-players';
    }

    public function description(): string
    {
        return 'Fetches trending players from the database based on adds or drops within the last 24 hours. Returns players ordered by trending value in descending order.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => ['add', 'drop'],
                    'description' => 'The type of trending data to fetch: "add" for players being added, "drop" for players being dropped',
                ],
            ],
            'required' => ['type'],
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
            'title' => 'Fetch Trending Players',
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
     * Fetches trending players from the database based on the specified type
     * and returns them ordered by trending value in descending order.
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
            'type' => ['required', 'string', 'in:add,drop'],
        ]);

        if ($validator->fails()) {
            throw new JsonRpcErrorException(
                message: 'Validation failed: '.$validator->errors()->first(),
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $type = $arguments['type'];
        $column = $type === 'add' ? 'adds_24h' : 'drops_24h';

        try {
            // Query database directly for players with non-null trending values
            $players = Player::whereNotNull($column)
                ->orderBy($column, 'desc')
                ->get();

            return [
                'success' => true,
                'data' => $players->toArray(),
                'count' => $players->count(),
                'message' => "Successfully fetched {$players->count()} trending players for type '{$type}'",
                'metadata' => [
                    'type' => $type,
                    'column' => $column,
                    'executed_at' => now()->toISOString(),
                ],
            ];

        } catch (\Exception $e) {
            // Log the error for debugging
            logger('FetchTrendingPlayersTool execution failed', [
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
