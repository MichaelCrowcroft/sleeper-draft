<?php

namespace App\MCP\Tools;

use App\Http\Resources\PlayerResource;
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

        $query = Player::whereNotNull('adp')
            ->orderBy('adp', 'asc');

        if ($position) {
            $query->where('position', $position);
        }

        $players = $query->get();

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
