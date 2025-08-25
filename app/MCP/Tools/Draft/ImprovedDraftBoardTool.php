<?php

namespace App\MCP\Tools\Draft;

use App\Services\ImprovedFantasyDataService;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class ImprovedDraftBoardTool implements ToolInterface
{
    public function name(): string
    {
        return 'improved_draft_board';
    }

    public function description(): string
    {
        return 'Build a draft board using improved data validation and fallbacks.';
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
                'use_improved_data' => ['type' => 'boolean', 'default' => true],
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
        /** @var ImprovedFantasyDataService $dataService */
        $dataService = LaravelApp::make(ImprovedFantasyDataService::class);

        $sport = $arguments['sport'] ?? 'nfl';
        $season = $arguments['season'] ?? date('Y');
        $week = (int) ($arguments['week'] ?? 1);
        $format = $arguments['format'] ?? 'redraft';
        $limit = (int) ($arguments['limit'] ?? 300);
        $tierGap = (float) ($arguments['tier_gaps'] ?? 10.0);
        $useImprovedData = (bool) ($arguments['use_improved_data'] ?? true);

        // Get validated data with fallbacks
        if ($useImprovedData) {
            $adp = $dataService->getValidatedAdp($season, $format, $sport);
            $projections = $dataService->getValidatedProjections($season, $week, $sport);
        } else {
            // Use original Sleeper SDK directly (for comparison)
            $sleeperSdk = LaravelApp::make(\App\Services\SleeperSdk::class);
            $adp = $sleeperSdk->getAdp($season, $format, $sport);
            $projections = $sleeperSdk->getWeeklyProjections($season, $week, $sport);
        }

        $sleeperSdk = LaravelApp::make(\App\Services\SleeperSdk::class);
        $catalog = $sleeperSdk->getPlayersCatalog($sport);

        // Build the same logic as the original tool but with better data
        $result = $this->buildDraftBoard($adp, $projections, $catalog, $limit, $tierGap);

        // Add data quality indicators
        $result['data_quality'] = [
            'adp_source' => $this->getAdpSourceInfo($adp),
            'projections_available' => !empty($projections) && count($projections) > 0,
            'players_with_adp' => count($adp),
            'players_with_projections' => $this->countPlayersWithProjections($projections),
            'using_improved_data' => $useImprovedData,
        ];

        return $result;
    }

    private function buildDraftBoard(array $adp, array $projections, array $catalog, int $limit, float $tierGap): array
    {
        $adpIndex = [];
        foreach ($adp as $row) {
            $adpIndex[(string) ($row['player_id'] ?? '')] = (float) ($row['adp'] ?? 999.0);
        }

        // Build board from market first (ADP), limiting to offensive skill positions only
        $allowedPositions = ['QB', 'RB', 'WR', 'TE'];
        $rows = [];
        $seen = [];

        // 1) ADP ordered by ADP asc
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
            $adpVal = $adpIndex[$pid] ?? 999.0;
            $score = $proj + max(0.0, (200.0 - min(200.0, $adpVal))) / 20.0;

            $rows[] = [
                'player_id' => $pid,
                'name' => $meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')),
                'position' => $pos,
                'team' => $meta['team'] ?? null,
                'adp' => $adpVal,
                'adp_sleeper' => $adpVal,
                'adp_espn' => null,
                'projected_points' => $proj,
                'score' => $score,
            ];
            $seen[$pid] = true;
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
                'espn' => false,
            ],
        ];
    }

    private function getAdpSourceInfo(array $adp): string
    {
        if (empty($adp)) {
            return 'no_data';
        }

        $sources = [];
        foreach ($adp as $player) {
            $source = $player['source'] ?? 'unknown';
            $sources[$source] = ($sources[$source] ?? 0) + 1;
        }

        arsort($sources);
        $primarySource = array_key_first($sources);

        return match($primarySource) {
            'sleeper_stats' => 'sleeper_official',
            'espn_fallback' => 'espn_fallback',
            'consensus_synthetic' => 'consensus_fallback',
            'heuristic_synthetic' => 'heuristic_fallback',
            'fallback_trending' => 'trending_fallback',
            default => 'unknown',
        };
    }

    private function countPlayersWithProjections(array $projections): int
    {
        $count = 0;
        foreach ($projections as $playerId => $proj) {
            $points = (float) (($proj['pts_half_ppr'] ?? $proj['pts_ppr'] ?? $proj['pts_std'] ?? 0));
            if ($points > 0) {
                $count++;
            }
        }
        return $count;
    }
}
