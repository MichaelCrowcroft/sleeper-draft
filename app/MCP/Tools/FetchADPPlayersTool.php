<?php

namespace App\MCP\Tools;

use App\Actions\Players\ListPlayersByAdp;
use App\Http\Resources\PlayerResource;
use App\MCP\Support\ToolHelpers;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchADPPlayersTool implements ToolInterface
{
    use ToolHelpers;

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

    public function annotations(): array
    {
        return [
            'title' => 'Fetch ADP Players',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,

            // Custom annotations
            'category' => 'fantasy-sports',
            'data_source' => 'database',
            'cache_recommended' => true,
        ];
    }

    public function execute(array $arguments): mixed
    {
        $arguments = $this->normalizeArgumentsGeneric(
            $arguments,
            aliases: ['pos' => 'position'],
            stringKeys: ['position']
        );

        $this->validateOrFail($arguments, [
            'position' => ['nullable', 'string', 'max:10'],
        ]);

        $position = $arguments['position'] ?? null;

        // Delegate fetching to an Action for reuse
        $players = app(ListPlayersByAdp::class)->execute($position);

        $filterDescription = $position ? " for position '{$position}'" : ' for all positions';

        return [
            'success' => true,
            'data' => PlayerResource::collection($players),
            'count' => $players->count(),
            'message' => "Successfully fetched {$players->count()} players by ADP{$filterDescription}",
            'metadata' => [
                'position_filter' => $position,
                'order_by' => 'adp',
                'order_direction' => 'asc',
                'executed_at' => now()->toISOString(),
            ],
        ];
    }
}
