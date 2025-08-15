<?php

namespace App\MCP\Tools\Espn;

use App\MCP\Tools\BaseTool;
use App\Services\EspnSdk;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;

class AdpProxyGetTool extends BaseTool
{
    public function name(): string
    {
        return 'espn_adp_proxy_get';
    }

    public function description(): string
    {
        return 'Get ESPN ADP proxy values from the Fantasy API (averageDraftPosition or rank fallback). Attempts to map to Sleeper player_id by name.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['season'],
            'properties' => [
                'season' => ['type' => 'integer'],
                'view' => ['type' => 'string', 'default' => 'kona_player_info'],
                'limit' => ['type' => 'integer', 'default' => 300],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
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
        /** @var EspnSdk $espn */
        $espn = LaravelApp::make(EspnSdk::class);
        /** @var SleeperSdk $sleeper */
        $sleeper = LaravelApp::make(SleeperSdk::class);

        $season = (int) $this->getParam($arguments, 'season', required: true);
        $view = (string) ($arguments['view'] ?? 'kona_player_info');
        $limit = (int) ($arguments['limit'] ?? 300);
        $sport = (string) ($arguments['sport'] ?? 'nfl');

        $espnPlayers = $espn->getFantasyPlayers($season, $view, $limit);
        $catalog = $sleeper->getPlayersCatalog($sport);

        $nameToPid = [];
        foreach ($catalog as $pidKey => $meta) {
            $pid = (string) ($meta['player_id'] ?? $pidKey);
            $fullName = (string) ($meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')));
            $n = self::normalizeName($fullName);
            if ($n !== '') {
                $nameToPid[$n] = $pid;
            }
        }

        $list = [];
        foreach ($espnPlayers as $item) {
            $adp = null;
            if (isset($item['averageDraftPosition']) && is_numeric($item['averageDraftPosition'])) {
                $adp = (float) $item['averageDraftPosition'];
            } elseif (isset($item['draftRanksByRankType']) && is_array($item['draftRanksByRankType'])) {
                // Prefer PPR rankings for redraft format
                $rankType = $item['draftRanksByRankType']['PPR'] ?? ($item['draftRanksByRankType']['STANDARD'] ?? null);
                if (is_array($rankType) && isset($rankType['rank']) && is_numeric($rankType['rank'])) {
                    $adp = (float) $rankType['rank'];
                }
            }
            if ($adp === null) {
                continue;
            }

            $espnName = (string) ($item['fullName'] ?? ($item['player']['fullName'] ?? (($item['firstName'] ?? '').' '.($item['lastName'] ?? ''))));
            $norm = self::normalizeName($espnName);
            $pid = $nameToPid[$norm] ?? null;

            $list[] = [
                'player_id' => $pid,
                'name' => $espnName,
                'adp_proxy' => $adp,
                'source' => 'espn',
            ];
        }

        return ['season' => $season, 'view' => $view, 'count' => count($list), 'items' => $list];
    }

    private static function normalizeName(string $name): string
    {
        $n = strtolower(trim($name));
        // Remove common suffixes for better matching
        $n = preg_replace('/\s+(jr|sr|ii|iii|iv)\.?$/i', '', $n ?? '');
        // Remove punctuation and special characters, keep spaces
        $n = preg_replace('/[^a-z\s]/', '', $n ?? '');
        // Normalize whitespace
        $n = preg_replace('/\s+/', ' ', trim($n ?? ''));

        return $n ?? '';
    }
}
