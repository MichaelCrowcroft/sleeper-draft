<?php

namespace App\MCP\Tools\Draft;

use App\Services\EspnSdk;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class DraftBoardBuildTool implements ToolInterface
{
    public function name(): string
    {
        return 'draft_board_build';
    }

    public function description(): string
    {
        return 'Build a draft board from ADP + projections with simple positional tiers.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
                'format' => ['type' => 'string', 'enum' => ['redraft', 'dynasty', 'bestball'], 'default' => 'redraft'],
                'tier_gaps' => ['type' => 'number', 'default' => 10.0],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'default' => 300],
                'blend_adp' => ['type' => 'boolean', 'default' => true],
                'espn_view' => ['type' => 'string', 'default' => 'kona_player_info'],
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
        // Resolve season/week if not provided
        $state = $sdk->getState($sport);
        $season = (string) ($arguments['season'] ?? ($state['season'] ?? date('Y')));
        $week = (int) ($arguments['week'] ?? (int) ($state['week'] ?? 1));
        $format = $arguments['format'] ?? 'redraft';
        $limit = (int) ($arguments['limit'] ?? 300);
        $tierGap = (float) ($arguments['tier_gaps'] ?? 10.0);

        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);
        // Fallback to Week 1 projections if the target season/week has no data (e.g., preseason for future seasons)
        if (empty($projections)) {
            $projections = $sdk->getWeeklyProjections($season, 1, $sport);
        }
        // Enable trending fallback for seasons where Sleeper has not published ADP yet (e.g., 2025 early offseason)
        $adp = $sdk->getAdp($season, $format, $sport, ttlSeconds: null, allowTrendingFallback: true);

        $adpIndex = [];
        foreach ($adp as $row) {
            $adpIndex[(string) ($row['player_id'] ?? '')] = (float) ($row['adp'] ?? 999.0);
        }

        // Optionally blend ESPN ADP when available
        $blendAdp = (bool) ($arguments['blend_adp'] ?? true);
        $espnAdpIndex = [];
        if ($blendAdp) {
            $espnView = (string) ($arguments['espn_view'] ?? 'kona_player_info');
            $espnPlayers = $espn->getFantasyPlayers((int) $season, $espnView, 2000);

            // Build ESPN ID and name maps → Sleeper player_id
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

            foreach ($espnPlayers as $item) {
                // Try to pull a reasonable ADP-like value from ESPN payload
                $adpCandidate = null;
                if (isset($item['averageDraftPosition']) && is_numeric($item['averageDraftPosition'])) {
                    $adpCandidate = (float) $item['averageDraftPosition'];
                } elseif (isset($item['draftRanksByRankType']) && is_array($item['draftRanksByRankType'])) {
                    // Fallback: treat rank as an ADP proxy if present
                    $rankType = $item['draftRanksByRankType']['STANDARD'] ?? ($item['draftRanksByRankType']['PPR'] ?? null);
                    if (is_array($rankType) && isset($rankType['rank']) && is_numeric($rankType['rank'])) {
                        $adpCandidate = (float) $rankType['rank'];
                    }
                }

                if ($adpCandidate === null) {
                    continue;
                }

                // Prefer ESPN ID mapping when available; fallback to name
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
                    $normName = self::normalizeName($espnName);
                    if ($normName !== '' && isset($nameToPid[$normName])) {
                        $pid = $nameToPid[$normName];
                    }
                }
                if ($pid === null) {
                    continue;
                }
                // Keep best (lowest) ADP proxy if duplicates occur
                if (! isset($espnAdpIndex[$pid]) || $adpCandidate < $espnAdpIndex[$pid]) {
                    $espnAdpIndex[$pid] = $adpCandidate;
                }
            }
        }

        // Build board from market first (ADP), limiting to offensive skill positions only
        $allowedPositions = ['QB', 'RB', 'WR', 'TE'];
        $rows = [];
        $seen = [];

        // 1) Sleeper ADP ordered by ADP asc
        usort($adp, function ($a, $b) {
            $adpA = (float) ($a['adp'] ?? 999.0);
            $adpB = (float) ($b['adp'] ?? 999.0);

            return $adpA <=> $adpB;
        });

        foreach ($adp as $row) {
            if (count($rows) >= $limit) {
                break;
            }
            $pid = (string) ($row['player_id'] ?? '');
            if ($pid === '' || isset($seen[$pid]) || ! isset($catalog[$pid])) {
                continue;
            }
            $meta = $catalog[$pid];
            $pos = strtoupper((string) ($meta['position'] ?? ''));
            if (! in_array($pos, $allowedPositions, true)) {
                continue;
            }
            $proj = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
            $adpSleeper = $adpIndex[$pid] ?? null;
            $adpEspn = $espnAdpIndex[$pid] ?? null;
            $adpVal = $adpSleeper !== null && $adpEspn !== null
                ? ($adpSleeper + $adpEspn) / 2.0
                : ($adpSleeper ?? $adpEspn ?? 999.0);
            $score = $proj + max(0.0, (200.0 - min(200.0, $adpVal))) / 20.0;

            $rows[] = [
                'player_id' => $pid,
                'name' => $meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')),
                'position' => $pos,
                'team' => $meta['team'] ?? null,
                'adp' => $adpVal,
                'adp_sleeper' => $adpSleeper,
                'adp_espn' => $adpEspn,
                'projected_points' => $proj,
                'score' => $score,
            ];
            $seen[$pid] = true;
        }

        // 2) Supplement with ESPN market ranks where available
        if (count($rows) < $limit && ! empty($espnAdpIndex)) {
            asort($espnAdpIndex, SORT_NUMERIC); // best (lowest) first
            foreach ($espnAdpIndex as $pid => $espnVal) {
                if (count($rows) >= $limit) {
                    break;
                }
                $pid = (string) $pid;
                if (isset($seen[$pid]) || ! isset($catalog[$pid])) {
                    continue;
                }
                $meta = $catalog[$pid];
                $pos = strtoupper((string) ($meta['position'] ?? ''));
                if (! in_array($pos, $allowedPositions, true)) {
                    continue;
                }
                $proj = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
                $adpSleeper = $adpIndex[$pid] ?? null;
                $adpEspn = (float) $espnVal;
                $adpVal = $adpSleeper !== null ? ($adpSleeper + $adpEspn) / 2.0 : $adpEspn;
                $score = $proj + max(0.0, (200.0 - min(200.0, $adpVal))) / 20.0;

                $rows[] = [
                    'player_id' => $pid,
                    'name' => $meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')),
                    'position' => $pos,
                    'team' => $meta['team'] ?? null,
                    'adp' => $adpVal,
                    'adp_sleeper' => $adpSleeper,
                    'adp_espn' => $adpEspn,
                    'projected_points' => $proj,
                    'score' => $score,
                ];
                $seen[$pid] = true;
            }
        }

        // 3) If still short, include top projected players (offensive positions only)
        if (count($rows) < $limit && ! empty($projections)) {
            // Build a list of candidates by projection
            $projectionCandidates = [];
            foreach ($projections as $pid => $p) {
                $pid = (string) $pid;
                if (isset($seen[$pid]) || ! isset($catalog[$pid])) {
                    continue;
                }
                $meta = $catalog[$pid];
                $pos = strtoupper((string) ($meta['position'] ?? ''));
                if (! in_array($pos, $allowedPositions, true)) {
                    continue;
                }
                $pts = (float) (($p['pts_half_ppr'] ?? $p['pts_ppr'] ?? $p['pts_std'] ?? 0));
                if ($pts <= 0) {
                    continue;
                }
                $projectionCandidates[] = [
                    'pid' => $pid,
                    'pos' => $pos,
                    'pts' => $pts,
                ];
            }
            usort($projectionCandidates, fn ($a, $b) => $b['pts'] <=> $a['pts']);
            foreach ($projectionCandidates as $c) {
                if (count($rows) >= $limit) {
                    break;
                }
                $pid = $c['pid'];
                $meta = $catalog[$pid];
                $pos = $c['pos'];
                $proj = (float) $c['pts'];
                $adpSleeper = $adpIndex[$pid] ?? null;
                $adpEspn = $espnAdpIndex[$pid] ?? null;
                $adpVal = $adpSleeper !== null && $adpEspn !== null
                    ? ($adpSleeper + $adpEspn) / 2.0
                    : ($adpSleeper ?? $adpEspn ?? 999.0);
                $score = $proj + max(0.0, (200.0 - min(200.0, $adpVal))) / 20.0;

                $rows[] = [
                    'player_id' => $pid,
                    'name' => $meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')),
                    'position' => $pos,
                    'team' => $meta['team'] ?? null,
                    'adp' => $adpVal,
                    'adp_sleeper' => $adpSleeper,
                    'adp_espn' => $adpEspn,
                    'projected_points' => $proj,
                    'score' => $score,
                ];
                $seen[$pid] = true;
            }
        }

        // Final ordering by score and limit
        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);
        $rows = array_slice($rows, 0, $limit);

        // Build tiers per position using score gaps
        $tiers = [];
        foreach (['QB', 'RB', 'WR', 'TE'] as $pos) {
            $posRows = array_values(array_filter($rows, fn ($r) => ($r['position'] ?? null) === $pos));
            usort($posRows, fn ($a, $b) => $b['score'] <=> $a['score']);
            $tier = 1;
            $last = null;
            foreach ($posRows as $idx => $r) {
                if ($last !== null && ($last - $r['score']) >= $tierGap) {
                    $tier++;
                    $last = $r['score'];
                }
                if ($last === null) {
                    $last = $r['score'];
                }
                $r['tier'] = $tier;
                $tiers[$pos][] = $r;
            }
        }

        return [
            'board' => $rows,
            'tiers' => $tiers,
            'adp_sources' => [
                'sleeper' => true,
                'espn' => $blendAdp,
            ],
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
