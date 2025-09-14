<?php

namespace App\MCP\Tools;

use App\Models\Player;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchPlayerTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-player';
    }

    public function description(): string
    {
        return 'Fetch comprehensive player data including stats, projections, volatility metrics, and performance analysis. Accepts either a player ID or search term to find a player.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'player_id' => [
                    'type' => 'string',
                    'description' => 'The Sleeper player ID to fetch data for',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Search term to find a player by name (first name, last name, or full name)',
                ],
            ],
            'oneOf' => [
                ['required' => ['player_id']],
                ['required' => ['search']],
            ],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Fetch Player Details',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
            'category' => 'fantasy-sports',
            'data_source' => 'database',
            'cache_recommended' => true,
            'notes' => 'Returns all data displayed on the player show page including stats, projections, volatility analysis, and performance charts.',
        ];
    }

    public function execute(array $arguments): mixed
    {
        $player = null;

        if (isset($arguments['player_id'])) {
            $player = Player::where('player_id', $arguments['player_id'])
                ->where('active', true)
                ->first();
        } elseif (isset($arguments['search'])) {
            $searchTerm = trim($arguments['search']);
            $player = Player::search($searchTerm)
                ->active()
                ->playablePositions()
                ->leftJoin('player_season_summaries', function ($join) {
                    $join->on('players.player_id', '=', 'player_season_summaries.player_id')
                         ->where('player_season_summaries.season', '=', 2024);
                })
                ->orderByRaw('COALESCE(player_season_summaries.total_points, 0) DESC')
                ->orderBy('players.first_name')
                ->orderBy('players.last_name')
                ->select('players.*')
                ->first();
        }

        if (! $player) {
            return [
                'success' => false,
                'error' => 'Player not found',
                'message' => isset($arguments['player_id'])
                    ? 'No player found with ID: '.$arguments['player_id']
                    : 'No player found matching search: '.$arguments['search'],
            ];
        }

        // Load necessary relationships
        $player->load([
            'stats2024',
            'seasonSummaries' => fn ($q) => $q->where('season', 2024),
            'projections2025',
        ]);

        $data = $this->collectPlayerData($player);

        return [
            'success' => true,
            'data' => $data,
            'message' => 'Fetched comprehensive data for '.$player->first_name.' '.$player->last_name,
            'metadata' => [
                'player_id' => $player->player_id,
                'fetched_at' => now()->toISOString(),
                'data_sources' => [
                    'player_table' => true,
                    'stats_2024' => true,
                    'projections_2025' => true,
                    'season_summaries' => true,
                ],
            ],
        ];
    }

    private function collectPlayerData(Player $player): array
    {
        // Basic player info
        $basicInfo = [
            'player_id' => $player->player_id,
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'full_name' => $player->full_name,
            'position' => $this->getPosition($player),
            'team' => $player->team,
            'age' => $player->age,
            'height' => $player->height,
            'weight' => $player->weight,
            'college' => $player->college,
            'injury_status' => $player->injury_status,
            'injury_body_part' => $player->injury_body_part,
            'adp' => $player->adp,
            'adp_formatted' => $player->adp_formatted,
            'adds_24h' => $player->adds_24h,
            'drops_24h' => $player->drops_24h,
            'bye_week' => $player->bye_week,
            'active' => $player->active,
        ];

        // 2024 Season data
        $stats2024 = $player->getSeason2024Totals();
        $summary2024 = $player->getSeason2024Summary();

        // 2025 Projections
        $projections2025 = $player->getSeason2025ProjectionSummary();
        $projectionDistribution = $this->calculateProjectionDistribution($projections2025);

        // Weekly data
        $weeklyStats = $player->getStatsForSeason(2024)->get();
        $weeklyProjections = $player->getProjectionsForSeason(2025)->get();

        $weeklyActualPoints = $this->getWeeklyActualPoints($weeklyStats);
        $weeklyProjectedPoints = $this->getWeeklyProjectedPoints($weeklyProjections);

        // Chart data
        $boxPlot = $this->getBoxPlotData($weeklyActualPoints, $weeklyProjectedPoints);
        $actualMedian2024 = $this->getActualMedian2024($weeklyActualPoints);
        $box2024Horizontal = $this->getBox2024Horizontal($weeklyActualPoints);

        // Position-specific stats
        $positionStats = $this->getPositionStats($player->position, $stats2024);

        return [
            'basic_info' => $basicInfo,
            'season_2024' => [
                'stats' => $stats2024,
                'summary' => $summary2024,
                'weekly_points' => $weeklyActualPoints,
                'median_ppg' => $actualMedian2024,
            ],
            'season_2025' => [
                'projections' => $projections2025,
                'projection_distribution' => $projectionDistribution,
                'weekly_projections' => $weeklyProjectedPoints,
            ],
            'charts' => [
                'box_plot' => $boxPlot,
                'box_2024_horizontal' => $box2024Horizontal,
            ],
            'position_stats' => $positionStats,
            'raw_data' => [
                'weekly_stats' => $weeklyStats->toArray(),
                'weekly_projections' => $weeklyProjections->toArray(),
            ],
        ];
    }

    private function getPosition(Player $player): string
    {
        // Use fantasy_positions if available, fallback to position
        $fantasyPositions = $player->fantasy_positions;
        if (is_array($fantasyPositions) && ! empty($fantasyPositions)) {
            return $fantasyPositions[0];
        }

        return $player->position ?? 'UNKNOWN';
    }

    private function calculateProjectionDistribution(array $proj): array
    {
        $avg = (float) ($proj['average_points_per_game'] ?? 0.0);
        $stdAbove = (float) ($proj['stddev_above'] ?? $avg);
        $stdBelow = (float) ($proj['stddev_below'] ?? $avg);
        $std = max(0.01, ($stdAbove - $avg));

        return [
            'avg' => $avg,
            'std' => $std,
            'lower' => max(0.0, $stdBelow),
            'upper' => max(0.0, $stdAbove),
            'min' => (float) ($proj['min_points'] ?? 0.0),
            'max' => (float) ($proj['max_points'] ?? 0.0),
        ];
    }

    private function getWeeklyActualPoints($weeklyStats): array
    {
        $series = [];
        foreach ($weeklyStats as $ws) {
            $stats = $ws->stats ?? [];
            if (isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr'])) {
                $series[] = ['week' => (int) $ws->week, 'value' => (float) $stats['pts_ppr']];
            }
        }
        usort($series, fn ($a, $b) => $a['week'] <=> $b['week']);

        return $series;
    }

    private function getWeeklyProjectedPoints($weeklyProjections): array
    {
        $series = [];
        foreach ($weeklyProjections as $wp) {
            $stats = $wp->stats ?? [];
            $val = null;
            if (isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr'])) {
                $val = (float) $stats['pts_ppr'];
            } elseif (isset($wp->pts_ppr) && is_numeric($wp->pts_ppr)) {
                $val = (float) $wp->pts_ppr;
            }
            if ($val !== null) {
                $series[] = ['week' => (int) $wp->week, 'value' => $val];
            }
        }
        usort($series, fn ($a, $b) => $a['week'] <=> $b['week']);

        return $series;
    }

    private function getBoxPlotData(array $actVals, array $prjVals): array
    {
        $actBox = $this->computeBox(array_map(fn ($p) => (float) $p['value'], $actVals));
        $prjBox = $this->computeBox(array_map(fn ($p) => (float) $p['value'], $prjVals));

        $width = 360.0;
        $height = 180.0;
        $padL = 34.0;
        $padR = 16.0;
        $padT = 8.0;
        $padB = 26.0;
        $plotH = $height - $padT - $padB;
        $plotW = $width - $padL - $padR;

        $boxes = array_values(array_filter([
            ['label' => '2024', 'stats' => $actBox],
            ['label' => '2025', 'stats' => $prjBox],
        ], fn ($it) => ! is_null($it['stats'])));

        if (empty($boxes)) {
            return [
                'width' => $width,
                'height' => $height,
                'items' => [],
                'ticks' => [],
            ];
        }

        $minVal = min(array_map(fn ($b) => $b['stats']['min'], $boxes));
        $maxVal = max(array_map(fn ($b) => $b['stats']['max'], $boxes));
        if ($minVal === $maxVal) {
            $minVal = max(0.0, $minVal - 1.0);
            $maxVal = $maxVal + 1.0;
        }

        $scaleY = function (float $v) use ($minVal, $maxVal, $padT, $plotH) {
            $t = ($v - $minVal) / ($maxVal - $minVal);

            return $padT + (1.0 - $t) * $plotH;
        };

        $n = count($boxes);
        $slotW = $plotW / max(1, $n);
        $boxW = min(36.0, $slotW * 0.4);

        $items = [];
        foreach ($boxes as $i => $b) {
            $xCenter = $padL + ($i + 0.5) * $slotW;
            $s = $b['stats'];
            $yMin = round($scaleY($s['min']), 2);
            $yQ1 = round($scaleY($s['q1']), 2);
            $yMedian = round($scaleY($s['median']), 2);
            $yQ3 = round($scaleY($s['q3']), 2);
            $yMax = round($scaleY($s['max']), 2);

            $items[] = [
                'label' => $b['label'],
                'x' => round($xCenter, 2),
                'w' => round($boxW, 2),
                'yMin' => $yMin,
                'yQ1' => $yQ1,
                'yMedian' => $yMedian,
                'yQ3' => $yQ3,
                'yMax' => $yMax,
                'vMin' => $s['min'],
                'vQ1' => $s['q1'],
                'vMedian' => $s['median'],
                'vQ3' => $s['q3'],
                'vMax' => $s['max'],
            ];
        }

        $ticks = [];
        for ($k = 0; $k <= 3; $k++) {
            $val = $minVal + ($k / 3.0) * ($maxVal - $minVal);
            $ticks[] = ['y' => round($scaleY($val), 2), 'label' => number_format($val, 1)];
        }

        return [
            'width' => $width,
            'height' => $height,
            'items' => $items,
            'ticks' => $ticks,
            'padL' => $padL,
            'padT' => $padT,
            'plotH' => $plotH,
            'slotW' => $slotW,
        ];
    }

    private function computeBox(array $values): ?array
    {
        if (empty($values)) {
            return null;
        }
        sort($values);
        $n = count($values);

        $median = function (array $arr): float {
            $m = count($arr);
            $mid = intdiv($m, 2);
            if ($m % 2 === 0) {
                return ($arr[$mid - 1] + $arr[$mid]) / 2.0;
            }

            return (float) $arr[$mid];
        };

        $med = $median($values);
        if ($n <= 2) {
            $q1 = $values[0];
            $q3 = $values[$n - 1];
        } else {
            $mid = intdiv($n, 2);
            $lower = array_slice($values, 0, $mid);
            $upper = array_slice($values, ($n % 2 === 0) ? $mid : $mid + 1);
            $q1 = $median($lower);
            $q3 = $median($upper);
        }

        return [
            'min' => (float) min($values),
            'q1' => (float) $q1,
            'median' => (float) $med,
            'q3' => (float) $q3,
            'max' => (float) max($values),
        ];
    }

    private function getActualMedian2024(array $weeklyActualPoints): ?float
    {
        $vals = array_map(fn ($p) => (float) $p['value'], $weeklyActualPoints);
        if (empty($vals)) {
            return null;
        }
        sort($vals);
        $n = count($vals);
        $mid = intdiv($n, 2);
        if ($n % 2 === 0) {
            return ($vals[$mid - 1] + $vals[$mid]) / 2.0;
        }

        return (float) $vals[$mid];
    }

    private function getBox2024Horizontal(array $weeklyActualPoints): array
    {
        $actVals = array_map(fn ($p) => (float) $p['value'], $weeklyActualPoints);
        $stats = $this->computeBox($actVals);

        $width = 480.0;
        $height = 120.0;
        $padL = 24.0;
        $padR = 24.0;
        $padT = 20.0;
        $padB = 24.0;
        $plotW = $width - $padL - $padR;
        $yMid = $padT + ($height - $padT - $padB) / 2.0;
        $boxH = 24.0;

        if (! $stats) {
            return [
                'width' => $width,
                'height' => $height,
                'exists' => false,
            ];
        }

        $minVal = $stats['min'];
        $maxVal = $stats['max'];
        if ($minVal === $maxVal) {
            $minVal = max(0.0, $minVal - 1.0);
            $maxVal = $maxVal + 1.0;
        }

        $scaleX = function (float $v) use ($minVal, $maxVal, $padL, $plotW) {
            $t = ($v - $minVal) / ($maxVal - $minVal);

            return $padL + $t * $plotW;
        };

        $xMin = round($scaleX($stats['min']), 2);
        $xQ1 = round($scaleX($stats['q1']), 2);
        $xMedian = round($scaleX($stats['median']), 2);
        $xQ3 = round($scaleX($stats['q3']), 2);
        $xMax = round($scaleX($stats['max']), 2);

        return [
            'width' => $width,
            'height' => $height,
            'exists' => true,
            'yMid' => round($yMid, 2),
            'boxH' => $boxH,
            'xMin' => $xMin,
            'xQ1' => $xQ1,
            'xMedian' => $xMedian,
            'xQ3' => $xQ3,
            'xMax' => $xMax,
            'vMin' => $stats['min'],
            'vQ1' => $stats['q1'],
            'vMedian' => $stats['median'],
            'vQ3' => $stats['q3'],
            'vMax' => $stats['max'],
        ];
    }

    private function getPositionStats(string $position, array $stats2024): array
    {
        switch ($position) {
            case 'QB':
                return [
                    'pass_yd' => $stats2024['pass_yd'] ?? 0,
                    'pass_td' => $stats2024['pass_td'] ?? 0,
                    'pass_int' => $stats2024['pass_int'] ?? 0,
                    'pass_cmp' => $stats2024['pass_cmp'] ?? 0,
                    'pass_att' => $stats2024['pass_att'] ?? 0,
                    'rush_yd' => $stats2024['rush_yd'] ?? 0,
                    'rush_td' => $stats2024['rush_td'] ?? 0,
                ];
            case 'RB':
                return [
                    'rush_yd' => $stats2024['rush_yd'] ?? 0,
                    'rush_td' => $stats2024['rush_td'] ?? 0,
                    'rush_att' => $stats2024['rush_att'] ?? 0,
                    'rec' => $stats2024['rec'] ?? 0,
                    'rec_yd' => $stats2024['rec_yd'] ?? 0,
                    'rec_td' => $stats2024['rec_td'] ?? 0,
                    'rec_tgt' => $stats2024['rec_tgt'] ?? 0,
                ];
            case 'WR':
            case 'TE':
                return [
                    'rec' => $stats2024['rec'] ?? 0,
                    'rec_yd' => $stats2024['rec_yd'] ?? 0,
                    'rec_td' => $stats2024['rec_td'] ?? 0,
                    'rec_tgt' => $stats2024['rec_tgt'] ?? 0,
                    'rec_lng' => $stats2024['rec_lng'] ?? 0,
                    'rec_ypr' => $stats2024['rec_ypr'] ?? 0,
                ];
            case 'K':
                return [
                    'fgm' => $stats2024['fgm'] ?? 0,
                    'fga' => $stats2024['fga'] ?? 0,
                    'xpm' => $stats2024['xpm'] ?? 0,
                    'xpa' => $stats2024['xpa'] ?? 0,
                    'fg_pct' => isset($stats2024['fga']) && $stats2024['fga'] > 0
                        ? round(($stats2024['fgm'] ?? 0) / $stats2024['fga'] * 100, 1)
                        : 0,
                ];
            case 'DEF':
            case 'DST':
                return [
                    'def_int' => $stats2024['def_int'] ?? 0,
                    'def_sack' => $stats2024['def_sack'] ?? 0,
                    'def_tkl' => $stats2024['def_tkl'] ?? 0,
                    'def_ff' => $stats2024['def_ff'] ?? 0,
                    'def_td' => $stats2024['def_td'] ?? 0,
                    'def_pa' => $stats2024['def_pa'] ?? 0,
                ];
            default:
                return [];
        }
    }
}
