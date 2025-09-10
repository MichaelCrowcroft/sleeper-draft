<?php

namespace App\MCP\Tools;

use App\Actions\Players\ListTrendingPlayers;
use App\Http\Resources\PlayerResource;
use App\MCP\Support\ToolHelpers;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchTrendingPlayersTool implements ToolInterface
{
    use ToolHelpers;

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

    public function annotations(): array
    {
        return [
            'title' => 'Fetch Trending Players',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,

            'category' => 'fantasy-sports',
            'data_source' => 'database',
            'cache_recommended' => true,
        ];
    }

    public function execute(array $arguments): mixed
    {
        $arguments = $this->normalizeArgumentsGeneric(
            $arguments,
            aliases: ['trendType' => 'type'],
            stringKeys: ['type']
        );

        $this->validateOrFail($arguments, [
            'type' => ['required', 'string', 'in:add,drop'],
        ]);

        $type = $arguments['type'];
        $column = $type === 'add' ? 'adds_24h' : 'drops_24h';

        // Delegate to Action for reuse
        $players = app(ListTrendingPlayers::class)->execute($type);

        return [
            'success' => true,
            'data' => PlayerResource::collection($players),
            'count' => $players->count(),
            'message' => "Successfully fetched {$players->count()} trending players for type '{$type}'",
            'metadata' => [
                'type' => $type,
                'column' => $column,
                'executed_at' => now()->toISOString(),
            ],
        ];
    }
}
