<?php

namespace App\MCP\Tools;

use App\Actions\Players\BuildPlayersTable;
use App\Http\Resources\PlayerResource;
use App\MCP\Support\ToolHelpers;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchPlayersTableTool implements ToolInterface
{
    use ToolHelpers;

    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-players-table';
    }

    public function description(): string
    {
        return 'Fetch a paginated, enriched list of players for table display, with filtering and sorting.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => ['type' => 'string'],
                'position' => ['type' => 'string'],
                'team' => ['type' => 'string'],
                'league_id' => ['type' => 'string'],
                'fa_only' => ['type' => 'boolean'],
                'sort_by' => ['type' => 'string', 'enum' => ['name', 'position', 'team', 'adp', 'age']],
                'sort_direction' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'page' => ['type' => 'integer', 'minimum' => 1],
            ],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Fetch Players Table',
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
        // Normalize arguments
        $arguments = $this->normalizeArgumentsGeneric(
            $arguments,
            aliases: [
                'sortBy' => 'sort_by',
                'sortDirection' => 'sort_direction',
                'leagueId' => 'league_id',
                'faOnly' => 'fa_only',
                'perPage' => 'per_page',
            ],
            intKeys: ['per_page', 'page'],
            boolKeys: ['fa_only'],
            stringKeys: ['search', 'position', 'team', 'sort_by', 'sort_direction', 'league_id']
        );

        $perPage = (int) ($arguments['per_page'] ?? 25);

        // Respect explicit page if provided
        if (isset($arguments['page'])) {
            request()->merge(['page' => (int) $arguments['page']]);
        }

        $paginator = app(BuildPlayersTable::class)->execute([
            'search' => $arguments['search'] ?? '',
            'position' => $arguments['position'] ?? '',
            'team' => $arguments['team'] ?? '',
            'sortBy' => $arguments['sort_by'] ?? 'adp',
            'sortDirection' => $arguments['sort_direction'] ?? 'asc',
            'league_id' => $arguments['league_id'] ?? null,
            'fa_only' => (bool) ($arguments['fa_only'] ?? false),
            'per_page' => $perPage,
        ]);

        $players = [];
        foreach ($paginator->items() as $player) {
            $row = (new PlayerResource($player))->resolve();
            // add owner/is_rostered if present on model instance
            if (isset($player->owner)) {
                $row['owner'] = $player->owner;
            }
            if (isset($player->is_rostered)) {
                $row['is_rostered'] = (bool) $player->is_rostered;
            }
            if (isset($player->weekly_position_rank)) {
                $row['weekly_position_rank'] = $player->weekly_position_rank;
            }
            if (isset($player->season_2024_summary['position_rank'])) {
                $row['season_2024_position_rank'] = $player->season_2024_summary['position_rank'];
            }
            $players[] = $row;
        }

        return $this->buildListResponse(
            items: $players,
            message: 'Fetched '.count($players).' players for table display',
            metadata: [
                'filters' => [
                    'search' => $arguments['search'] ?? null,
                    'position' => $arguments['position'] ?? null,
                    'team' => $arguments['team'] ?? null,
                    'league_id' => $arguments['league_id'] ?? null,
                    'fa_only' => (bool) ($arguments['fa_only'] ?? false),
                    'sort_by' => $arguments['sort_by'] ?? 'adp',
                    'sort_direction' => $arguments['sort_direction'] ?? 'asc',
                ],
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ]
        );
    }
}
