<?php

namespace App\MCP\Tools;

use App\Actions\Players\FindPlayersSeasonData;
use App\Http\Resources\PlayerResource;
use App\MCP\Support\ToolHelpers;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchPlayerSeasonDataTool implements ToolInterface
{
    use ToolHelpers;

    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-player-season-data';
    }

    public function description(): string
    {
        return 'Returns last season stats + summary and current season projections + summary for a specific player by player_id or by name. If name matches multiple, all matches are returned.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'player_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper player ID to search for (exact match)',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Player name to search (case-insensitive partial match)',
                ],
            ],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Fetch Player Season Data',
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
            aliases: ['id' => 'player_id', 'playerId' => 'player_id'],
            stringKeys: ['player_id', 'name']
        );

        $this->validateOrFail($arguments, [
            'player_id' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
        ]);

        $playerId = $arguments['player_id'] ?? null;
        $name = $arguments['name'] ?? null;

        if (! $playerId && ! $name) {
            throw new JsonRpcErrorException(
                message: 'Provide either player_id or name',
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $mode = $playerId ? 'by_id' : 'by_name';

        // Delegate to Action for reuse
        $players = app(FindPlayersSeasonData::class)->execute($playerId, $name);

        if ($playerId && $players->isEmpty()) {
            throw new JsonRpcErrorException(
                message: 'Player not found for player_id='.$playerId,
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $data = $players->map(fn ($p) => (new PlayerResource($p))->resolve())->all();

        return $this->buildListResponse(
            items: $data,
            message: 'Fetched '.count($data).' player(s) season data',
            metadata: [
                'mode' => $mode,
                'filters' => [
                    'player_id' => $playerId,
                    'name' => $name,
                ],
                'seasons' => [
                    'stats' => 2024,
                    'projections' => 2025,
                ],
            ]
        );
    }
}
