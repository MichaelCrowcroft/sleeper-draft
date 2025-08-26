<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\EspnSdk;
use App\Services\SleeperSdk;
use App\Support\PlayerInfo;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class AdpGetTool implements ToolInterface
{
    public function name(): string
    {
        return 'adp_get';
    }

    public function description(): string
    {
        return 'Get current ADP/market values.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'season' => ['type' => 'string'],
                'format' => ['type' => 'string', 'enum' => ['redraft', 'dynasty', 'bestball'], 'default' => 'redraft'],
                // Prefer ESPN fallback when Sleeper lacks data (e.g., early offseason)
                'espn_fallback' => ['type' => 'boolean', 'default' => true],
                // Disable the older trending-based pseudo ADP by default
                'trending_fallback' => ['type' => 'boolean', 'default' => false],
                // ESPN tuning
                'espn_view' => ['type' => 'string', 'default' => 'mDraftDetail'],
                'espn_limit' => ['type' => 'integer', 'default' => 2000],
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
        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        /** @var EspnSdk $espn */
        $espn = LaravelApp::make(EspnSdk::class);
        $sport = $arguments['sport'] ?? 'nfl';
        $season = (string) ($arguments['season'] ?? ((string) (LaravelApp::make(SleeperSdk::class)->getState($sport)['season'] ?? date('Y'))));
        $format = $arguments['format'] ?? 'redraft';
        $espnFallback = (bool) ($arguments['espn_fallback'] ?? true);
        $trendingFallback = (bool) ($arguments['trending_fallback'] ?? false);
        $espnView = (string) ($arguments['espn_view'] ?? 'mDraftDetail');
        $espnLimit = (int) ($arguments['espn_limit'] ?? 2000);

        // First try Sleeper ADP; optionally suppress trending-based placeholder
        $adp = $sdk->getAdp($season, $format, $sport, ttlSeconds: null, allowTrendingFallback: $trendingFallback);

        // If no Sleeper ADP and ESPN fallback is allowed, construct an ADP proxy from ESPN
        if ($espnFallback && (empty($adp) || ! is_array($adp))) {
            $seasonInt = (int) $season;
            $espnPlayers = $espn->getFantasyPlayers($seasonInt, $espnView, $espnLimit);
            $catalog = $sdk->getPlayersCatalog($sport);
            $nameToPid = [];
            $espnIdToPid = [];
            foreach ($catalog as $pidKey => $meta) {
                $pid = (string) ($meta['player_id'] ?? $pidKey);
                $fullName = (string) ($meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')));
                $n = self::normalizeName($fullName);
                if ($n !== '') {
                    $nameToPid[$n] = $pid;
                }
                if (isset($meta['espn_id']) && $meta['espn_id'] !== null && $meta['espn_id'] !== '') {
                    $espnIdToPid[(string) $meta['espn_id']] = $pid;
                }
            }

            $list = [];
            foreach ($espnPlayers as $item) {
                $adpProxy = null;
                if (isset($item['averageDraftPosition']) && is_numeric($item['averageDraftPosition'])) {
                    $adpProxy = (float) $item['averageDraftPosition'];
                } elseif (isset($item['draftRanksByRankType']) && is_array($item['draftRanksByRankType'])) {
                    $rankType = $item['draftRanksByRankType']['STANDARD'] ?? ($item['draftRanksByRankType']['PPR'] ?? null);
                    if (is_array($rankType) && isset($rankType['rank']) && is_numeric($rankType['rank'])) {
                        $adpProxy = (float) $rankType['rank'];
                    }
                }
                if ($adpProxy === null) {
                    continue;
                }

                // Prefer ESPN ID mapping; fallback to name
                $pid = null;
                $espnId = null;
                if (isset($item['id']) && is_numeric($item['id'])) {
                    $espnId = (string) $item['id'];
                } elseif (isset($item['player']['id']) && is_numeric($item['player']['id'])) {
                    $espnId = (string) $item['player']['id'];
                } elseif (isset($item['playerId']) && is_numeric($item['playerId'])) {
                    $espnId = (string) $item['playerId'];
                }
                if ($espnId !== null && isset($espnIdToPid[$espnId])) {
                    $pid = $espnIdToPid[$espnId];
                } else {
                    $espnName = (string) ($item['fullName'] ?? ($item['player']['fullName'] ?? (($item['firstName'] ?? '').' '.($item['lastName'] ?? ''))));
                    $norm = self::normalizeName($espnName);
                    $pid = $nameToPid[$norm] ?? null;
                }
                if (! $pid) {
                    continue;
                }

                $list[] = [
                    'player_id' => $pid,
                    'adp' => $adpProxy,
                    'source' => 'espn',
                ];
            }

            $adp = $list;
        }

        // Enrich with player information
        $players = PlayerInfo::fetch(array_column($adp, 'player_id'), $sport);
        foreach ($adp as &$row) {
            $pid = (string) ($row['player_id'] ?? '');
            $info = $players[$pid] ?? ['name' => null, 'position' => null, 'team' => null];
            $row['name'] = $info['name'];
            $row['position'] = $info['position'];
            $row['team'] = $info['team'];
        }
        unset($row);

        return ['season' => $season, 'format' => $format, 'adp' => $adp];
    }

    private static function normalizeName(string $name): string
    {
        $n = strtolower(trim($name));
        $n = preg_replace('/[^a-z\s]/', '', $n ?? '');
        $n = preg_replace('/\s+/', ' ', $n ?? '');

        return $n ?? '';
    }
}
