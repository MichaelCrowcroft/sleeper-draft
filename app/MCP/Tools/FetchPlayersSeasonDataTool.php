<?php

namespace App\MCP\Tools;

use App\MCP\Support\ToolHelpers;
use Illuminate\Support\Facades\Validator;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchPlayersSeasonDataTool implements ToolInterface
{
    use ToolHelpers;

    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-players-season-data';
    }

    public function description(): string
    {
        return 'Returns all players with last season stats + summary, current season projections + summary, and full projection data for the upcoming matchweek. Optionally filter by position.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'league_id' => [
                    'type' => 'string',
                    'description' => 'Optional Sleeper league ID. When provided, each player will include league team info or Free Agent.',
                ],
                'position' => [
                    'type' => 'string',
                    'description' => 'Optional position filter (e.g., QB, RB, WR, TE, K, DEF)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 1000,
                    'description' => 'Optional limit of players to return (default 10)',
                ],
                'offset' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'Optional offset for pagination (default 0)',
                ],
                'cursor' => [
                    'type' => 'string',
                    'description' => 'Opaque cursor for pagination per MCP spec. If provided, overrides offset/limit.',
                ],
            ],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Fetch Players Season Data',
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
            aliases: [
                'pos' => 'position',
                'c' => 'cursor',
                'leagueId' => 'league_id',
            ],
            intKeys: ['limit', 'offset'],
            boolKeys: [],
            stringKeys: ['cursor', 'league_id', 'position']
        );

        $validator = Validator::make($arguments, [
            'position' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            throw new JsonRpcErrorException(
                message: 'Validation failed: '.$validator->errors()->first(),
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $position = $arguments['position'] ?? null;
        $cursor = $arguments['cursor'] ?? null;
        $leagueId = $arguments['league_id'] ?? null;

        // Determine paging from cursor or fallback to limit/offset
        $defaultPageSize = 10;
        $limit = (int) ($arguments['limit'] ?? $defaultPageSize);
        $offset = (int) ($arguments['offset'] ?? 0);
        if (is_string($cursor) && $cursor !== '') {
            $decoded = $this->decodeCursor($cursor);
            if ($decoded === null) {
                throw new JsonRpcErrorException(
                    message: 'Invalid cursor',
                    code: JsonRpcErrorCode::INVALID_REQUEST
                );
            }
            $limit = (int) ($decoded['limit'] ?? $defaultPageSize);
            $offset = (int) ($decoded['offset'] ?? 0);
            // Position in cursor (if present) takes precedence
            if (isset($decoded['position'])) {
                $position = $decoded['position'];
            }
            if (isset($decoded['league_id'])) {
                $leagueId = $decoded['league_id'];
            }
        }

        // Enforce sane bounds
        $limit = max(1, min(1000, $limit));
        $offset = max(0, $offset);

        // Delegate heavy lifting to Action
        $page = app(\App\Actions\Players\FetchPlayersSeasonDataPage::class)
            ->execute($position, $limit, $offset, $leagueId);

        $response = $this->buildListResponse(
            items: $page['players'],
            message: 'Fetched '.$page['count'].' players with season data',
            metadata: [
                'filters' => [
                    'position' => $position,
                ],
                'pagination' => [
                    'limit' => $page['limit'],
                    'offset' => $page['offset'],
                    'mode' => 'cursor',
                ],
                'seasons' => [
                    'stats' => 2024,
                    'projections' => 2025,
                ],
                'upcoming_week' => $page['upcomingWeek'],
                'league_id' => $leagueId,
            ]
        );

        // Keep top-level nextCursor for backward compatibility and tests
        $response['nextCursor'] = $page['nextCursor'];

        return $response;
    }

    /**
     * Build a mapping of player_id => league team name for a given Sleeper league
     * using shared Actions (rosters and users).
     */
    private function buildPlayerLeagueTeamMap(string $leagueId): array
    {
        // Kept for backward compatibility if used elsewhere, but now unused in execute().
        return app(\App\Actions\Sleeper\BuildPlayerLeagueTeamMap::class)->execute($leagueId);
    }
}
