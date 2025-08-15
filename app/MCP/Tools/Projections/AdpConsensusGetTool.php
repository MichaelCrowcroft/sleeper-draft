<?php

namespace App\MCP\Tools\Projections;

use App\Services\EspnSdk;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class AdpConsensusGetTool implements ToolInterface
{
    public function name(): string
    {
        return 'adp_consensus_get';
    }

    public function description(): string
    {
        return 'Get consensus ADP blended from Sleeper and ESPN proxy, with optional CSV import (FantasyPros). Returns per-source diagnostics and largest deltas.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['season'],
            'properties' => [
                'season' => ['type' => 'string'],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'format' => ['type' => 'string', 'enum' => ['redraft', 'dynasty', 'bestball'], 'default' => 'redraft'],
                'espn_view' => ['type' => 'string', 'default' => 'mDraftDetail'],
                'weights' => [
                    'type' => 'object',
                    'properties' => [
                        'sleeper' => ['type' => 'number', 'default' => 1.0],
                        'espn' => ['type' => 'number', 'default' => 1.0],
                        'csv' => ['type' => 'number', 'default' => 1.0],
                    ],
                    'additionalProperties' => false,
                ],
                'csv_url' => ['type' => 'string'],
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

        $sport = (string) ($arguments['sport'] ?? 'nfl');
        $season = (string) $arguments['season'];
        $format = (string) ($arguments['format'] ?? 'redraft');
        $espnView = (string) ($arguments['espn_view'] ?? 'mDraftDetail');
        $weights = (array) ($arguments['weights'] ?? ['sleeper' => 1.0, 'espn' => 1.0, 'csv' => 1.0]);
        $wSleeper = (float) ($weights['sleeper'] ?? 1.0);
        $wEspn = (float) ($weights['espn'] ?? 1.0);
        $wCsv = (float) ($weights['csv'] ?? 1.0);
        $csvUrl = isset($arguments['csv_url']) ? (string) $arguments['csv_url'] : null;

        $catalog = $sleeper->getPlayersCatalog($sport);
        $nameToPid = [];
        $espnIdToPid = [];
        foreach ($catalog as $pidKey => $meta) {
            $pid = (string) ($meta['player_id'] ?? $pidKey);
            $fullName = (string) ($meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')));
            $norm = self::normalizeName($fullName);
            if ($norm !== '') {
                $nameToPid[$norm] = $pid;
            }
            if (isset($meta['espn_id']) && $meta['espn_id'] !== null && $meta['espn_id'] !== '') {
                $espnIdToPid[(string) $meta['espn_id']] = $pid;
            }
        }

        // Sleeper ADP (no trending fallback)
        $sleeperAdp = [];
        foreach ($sleeper->getAdp($season, $format, $sport, ttlSeconds: null, allowTrendingFallback: false) as $row) {
            $pid = (string) ($row['player_id'] ?? '');
            $adpVal = isset($row['adp']) ? (float) $row['adp'] : null;
            if ($pid !== '' && $adpVal !== null) {
                $sleeperAdp[$pid] = $adpVal;
            }
        }

        // ESPN proxy ADP
        $espnAdp = [];
        foreach ($espn->getFantasyPlayers((int) $season, $espnView, 2000) as $item) {
            $adpCandidate = null;
            if (isset($item['averageDraftPosition']) && is_numeric($item['averageDraftPosition'])) {
                $adpCandidate = (float) $item['averageDraftPosition'];
            } elseif (isset($item['draftRanksByRankType']) && is_array($item['draftRanksByRankType'])) {
                $rankType = $item['draftRanksByRankType']['STANDARD'] ?? ($item['draftRanksByRankType']['PPR'] ?? null);
                if (is_array($rankType) && isset($rankType['rank']) && is_numeric($rankType['rank'])) {
                    $adpCandidate = (float) $rankType['rank'];
                }
            }
            if ($adpCandidate === null) {
                continue;
            }
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
            if ($pid !== null) {
                $espnAdp[$pid] = isset($espnAdp[$pid]) ? min($espnAdp[$pid], $adpCandidate) : $adpCandidate;
            }
        }

        // Optional CSV import (FantasyPros etc.) minimal parser: expects columns Name, ADP
        $csvAdp = [];
        if ($csvUrl) {
            try {
                $csv = @file_get_contents($csvUrl);
                if ($csv !== false) {
                    $lines = preg_split("/\r?\n/", $csv);
                    $header = null;
                    foreach ($lines as $line) {
                        if (trim($line) === '') {
                            continue;
                        }
                        $cols = str_getcsv($line);
                        if ($header === null) {
                            $header = array_map(fn ($h) => strtolower(trim($h)), $cols);

                            continue;
                        }
                        $row = [];
                        foreach ($cols as $i => $val) {
                            $row[$header[$i] ?? $i] = $val;
                        }
                        $name = (string) ($row['name'] ?? ($row['player'] ?? ''));
                        $adpStr = (string) ($row['adp'] ?? ($row['avg'] ?? ''));
                        if ($name === '' || $adpStr === '' || ! is_numeric($adpStr)) {
                            continue;
                        }
                        $norm = self::normalizeName($name);
                        $pid = $nameToPid[$norm] ?? null;
                        if ($pid !== null) {
                            $csvAdp[$pid] = (float) $adpStr;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore CSV errors; return available consensus
            }
        }

        // Build consensus using weighted average (winsorize extreme values to [1, 300])
        $allPids = array_unique(array_merge(array_keys($sleeperAdp), array_keys($espnAdp), array_keys($csvAdp)));
        $items = [];
        foreach ($allPids as $pid) {
            $vals = [];
            $weights = [];
            if (isset($sleeperAdp[$pid])) {
                $vals[] = max(1.0, min(300.0, (float) $sleeperAdp[$pid]));
                $weights[] = $wSleeper;
            }
            if (isset($espnAdp[$pid])) {
                $vals[] = max(1.0, min(300.0, (float) $espnAdp[$pid]));
                $weights[] = $wEspn;
            }
            if (isset($csvAdp[$pid])) {
                $vals[] = max(1.0, min(300.0, (float) $csvAdp[$pid]));
                $weights[] = $wCsv;
            }
            if (empty($vals)) {
                continue;
            }
            $weighted = 0.0;
            $sumW = 0.0;
            foreach ($vals as $i => $v) {
                $weighted += $v * $weights[$i];
                $sumW += $weights[$i];
            }
            $consensus = $sumW > 0 ? $weighted / $sumW : null;
            if ($consensus === null) {
                continue;
            }
            $items[] = [
                'player_id' => $pid,
                'adp_consensus' => round($consensus, 2),
                'adp_sleeper' => $sleeperAdp[$pid] ?? null,
                'adp_espn' => $espnAdp[$pid] ?? null,
                'adp_csv' => $csvAdp[$pid] ?? null,
            ];
        }

        usort($items, fn ($a, $b) => ($a['adp_consensus'] <=> $b['adp_consensus']));

        // Diagnostics: counts and largest deltas
        $top = array_slice($items, 0, 200);
        $mismatches = [];
        foreach ($top as $row) {
            $s = $row['adp_sleeper'];
            $e = $row['adp_espn'];
            if ($s !== null && $e !== null) {
                $mismatches[] = [
                    'player_id' => $row['player_id'],
                    'delta' => round((float) $e - (float) $s, 2),
                ];
            }
        }
        usort($mismatches, fn ($a, $b) => abs($b['delta']) <=> abs($a['delta']));
        $mismatches = array_slice($mismatches, 0, 25);

        return [
            'season' => $season,
            'format' => $format,
            'counts' => [
                'sleeper' => count($sleeperAdp),
                'espn' => count($espnAdp),
                'csv' => count($csvAdp),
                'consensus' => count($items),
            ],
            'items' => $items,
            'largest_mismatches' => $mismatches,
        ];
    }

    private static function normalizeName(string $name): string
    {
        $n = strtolower(trim($name));
        $n = preg_replace('/[^a-z\s]/', '', $n ?? '');
        $n = preg_replace('/\s+/', ' ', $n ?? '');

        return $n ?? '';
    }
}
