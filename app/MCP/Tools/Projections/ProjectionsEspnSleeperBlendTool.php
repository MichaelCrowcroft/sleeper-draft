<?php

namespace App\MCP\Tools\Projections;

use App\MCP\Tools\BaseTool;
use App\Services\EspnSdk;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;

class ProjectionsEspnSleeperBlendTool extends BaseTool
{
    public function name(): string
    {
        return 'projections_blend_espn_sleeper';
    }

    public function description(): string
    {
        return 'Blend ESPN fantasy player dataset with Sleeper projections and trending to produce a richer projection set.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer'],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'espn_view' => ['type' => 'string', 'default' => 'mDraftDetail'],
                'limit' => ['type' => 'integer', 'description' => 'Optional cap on number of ESPN fantasy players to fetch'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function annotations(): array
    {
        return [];
    }

    public function execute(array $arguments): mixed
    {
        /** @var SleeperSdk $sleeper */
        $sleeper = LaravelApp::make(SleeperSdk::class);
        /** @var EspnSdk $espn */
        $espn = LaravelApp::make(EspnSdk::class);

        [$season, $week] = $this->resolveSeasonWeek($arguments, $this->resolveArg($arguments, 'sport', 'nfl'));
        $espnSeason = (int) ($arguments['season'] ?? $season);
        $espnView = (string) ($arguments['espn_view'] ?? 'mDraftDetail');
        $limit = isset($arguments['limit']) ? (int) $arguments['limit'] : null;

        $espnPlayers = $espn->getFantasyPlayers($espnSeason, $espnView, $limit);
        $projections = $sleeper->getWeeklyProjections($season, (int) $week);
        $trending = $sleeper->getPlayersTrending('add', lookbackHours: 24 * 7, limit: 500);

        // Index Sleeper projections by player_id for quick lookup
        $projById = [];
        foreach ($projections as $row) {
            $pid = (string) ($row['player_id'] ?? '');
            if ($pid === '') {
                continue;
            }
            $projById[$pid] = $row;
        }

        // Index trending as rank map
        $trendRank = [];
        $rank = 1;
        foreach ($trending as $row) {
            $pid = (string) ($row['player_id'] ?? '');
            if ($pid === '') {
                continue;
            }
            $trendRank[$pid] = $rank++;
        }

        // Blend: attach projection + market rank to ESPN player items when playerId mappings are available
        // ESPN item identifiers differ; many datasets include ESPN playerId. We pass through raw ESPN item
        // and include any Sleeper matches by player_id when present in provided data rows.
        $blended = [];
        foreach ($espnPlayers as $item) {
            // Try common fields that may exist across ESPN views
            $pid = (string) ($item['playerId'] ?? $item['id'] ?? '');

            $match = $pid !== '' ? ($projById[$pid] ?? null) : null;
            $market = $pid !== '' ? ($trendRank[$pid] ?? null) : null;

            $blended[] = [
                'espn' => $item,
                'sleeper_projection' => $match,
                'market_trend_rank' => $market,
            ];
        }

        return [
            'season' => (string) $season,
            'week' => (int) $week,
            'count' => count($blended),
            'items' => $blended,
        ];
    }
}
