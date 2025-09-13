<?php

namespace App\Actions\Matchups;

use App\Models\Player;
use Illuminate\Support\Collection;

class EnrichMatchupsWithPlayerData
{
    public function execute(array $matchups, int $season, int $week): array
    {
        if (empty($matchups)) {
            return [];
        }

        $player_ids = collect($matchups)
            ->flatten(1)
            ->flatMap(function ($matchup) {
                $ids = [];
                $ids = array_merge($ids, $matchup['starters']);
                $ids = array_merge($ids, $matchup['players']);

                return $ids;
            })
            ->filter()
            ->unique()
            ->values();

        if ($player_ids->isEmpty()) {
            return $matchups;
        }

        $players = Player::whereIn('player_id', $player_ids)
            ->with([
                'projections' => function ($query) use ($season, $week) {
                    $query->where('season', $season)->where('week', $week);
                },
                'stats' => function ($query) use ($season, $week) {
                    $query->where('season', $season)->where('week', $week);
                },
                // Eager load season summaries to source volatility metrics
                'seasonSummaries',
            ])
            ->get()
            ->keyBy('player_id');

        $projectionStdDev = $this->getProjectionStandardDeviation();
        $positionCvThresholds = $this->getPositionCvThresholds();

        foreach ($matchups as &$matchup) {
            foreach ($matchup as &$team) {
                $team = $this->enrichTeam($team, $players, $season, $projectionStdDev, $positionCvThresholds);
                $team['projected_total'] = $this->calculateProjectedTotal($team);
                $team['confidence_interval'] = $this->calculateTeamConfidenceInterval($team, $projectionStdDev);
            }

            $teams = array_values($matchup);
            $home = $teams[0] ?? [];
            $away = $teams[1] ?? [];

            $home = $this->computeTeamDistribution($home, $projectionStdDev);
            $away = $this->computeTeamDistribution($away, $projectionStdDev);

            $win_probabilities = new ComputeWinProbability()->execute($home, $away);

            $matchup['win_probabilities'] = [
                'team_a_win_probability' => round($win_probabilities['home'] * 100, 1),
                'team_b_win_probability' => round($win_probabilities['away'] * 100, 1),
            ];
        }

        return $matchups;
    }

    private function enrichTeam(array $team, Collection $players, int $season, float $projectionStdDev, array $positionCvThresholds): array
    {
        return collect($team)
            ->transform(function ($value, $key) use ($players, $season, $projectionStdDev, $positionCvThresholds) {
                if ($key === 'starters' || $key === 'players') {
                    return collect($value)
                        ->map(fn ($player_id) => $this->enrichPlayer($player_id, $players, $season, $projectionStdDev, $positionCvThresholds))
                        ->all();
                }

                return $value;
            })
            ->all();
    }

    private function enrichPlayer(string $player_id, Collection $players, int $season, float $projectionStdDev, array $positionCvThresholds): array
    {
        $player = $players->get($player_id);

        $projection = $player->projections->first();
        $stats = $player->stats->first();

        // Determine projected and actual points for this week
        $actualPoints = null;
        if (isset($stats) && is_array($stats->stats ?? null)) {
            $actualPoints = $stats->stats['pts_ppr'] ?? null;
            $actualPoints = is_numeric($actualPoints) ? (float) $actualPoints : null;
        }

        $projectedPoints = null;
        if (isset($projection)) {
            // Prefer flattened column, else nested stats
            if (isset($projection->pts_ppr) && is_numeric($projection->pts_ppr)) {
                $projectedPoints = (float) $projection->pts_ppr;
            } elseif (is_array($projection->stats ?? null) && isset($projection->stats['pts_ppr']) && is_numeric($projection->stats['pts_ppr'])) {
                $projectedPoints = (float) $projection->stats['pts_ppr'];
            }
        }

        // Pull prior season summary for player-specific volatility (fallback to latest available)
        $summary = null;
        if ($player->relationLoaded('seasonSummaries')) {
            $summary = $player->seasonSummaries->firstWhere('season', $season - 1)
                ?? $player->seasonSummaries->sortByDesc('season')->first();
        }

        $volatility = is_array($summary->volatility ?? null) ? $summary->volatility : [];
        $cv = isset($volatility['coefficient_of_variation']) && is_numeric($volatility['coefficient_of_variation'])
            ? max(0.0, (float) $volatility['coefficient_of_variation'])
            : null;

        // Derive player-specific weekly stddev estimate
        $stddevFromSummary = null;
        if ($summary && is_numeric($summary->stddev_above) && is_numeric($summary->average_points_per_game)) {
            $stddevFromSummary = abs((float) $summary->stddev_above - (float) $summary->average_points_per_game);
        }

        $z = 1.645; // 90% CI
        $playerStdDev = null;
        if ($actualPoints !== null) {
            $playerStdDev = 0.0;
        } elseif ($cv !== null && $projectedPoints !== null && $cv > 0) {
            $playerStdDev = $cv * $projectedPoints;
        } elseif ($stddevFromSummary !== null && $stddevFromSummary > 0) {
            $playerStdDev = $stddevFromSummary;
        } else {
            $playerStdDev = $projectionStdDev; // global fallback
        }

        $range = null;
        if ($actualPoints !== null) {
            $range = [
                'base' => round($actualPoints, 1),
                'lower_90' => round($actualPoints, 1),
                'upper_90' => round($actualPoints, 1),
                'stddev' => 0.0,
                'source' => 'actual',
            ];
        } elseif ($projectedPoints !== null) {
            $margin = $z * (float) $playerStdDev;
            $lower = max(0.0, (float) $projectedPoints - $margin);
            $upper = (float) $projectedPoints + $margin;
            $range = [
                'base' => round((float) $projectedPoints, 1),
                'lower_90' => round($lower, 1),
                'upper_90' => round($upper, 1),
                'stddev' => round((float) $playerStdDev, 3),
                'source' => 'projection',
            ];
        }

        // Risk classification for UI (tighter bands)
        // Prefer CV from prior season; fallback to relative stddev this week
        $effectiveCv = null;
        if ($cv !== null) {
            $effectiveCv = $cv;
        } elseif ($projectedPoints !== null && $projectedPoints > 0 && $playerStdDev !== null) {
            $effectiveCv = min(2.0, (float) $playerStdDev / (float) $projectedPoints);
        }

        $riskProfile = null;
        if ($effectiveCv !== null) {
            $pos = $player->position ?? ($player->fantasy_positions[0] ?? null);
            $thresholds = ($pos && isset($positionCvThresholds[$pos])) ? $positionCvThresholds[$pos] : null;
            $safeCut = $thresholds['safe_upper'] ?? 0.20;
            $balancedCut = $thresholds['balanced_upper'] ?? 0.40;

            if ($effectiveCv <= $safeCut) {
                $riskProfile = 'safe';
            } elseif ($effectiveCv <= $balancedCut) {
                $riskProfile = 'balanced';
            } else {
                $riskProfile = 'volatile';
            }
        }

        return [
            'player_id' => $player->player_id,
            'name' => $player->full_name ?? ($player->first_name.' '.$player->last_name),
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'position' => $player->position,
            'team' => $player->team,
            'fantasy_positions' => $player->fantasy_positions,
            'active' => $player->active,
            'injury_status' => $player->injury_status,
            'projection' => $projection,
            'stats' => $stats,
            'volatility' => $volatility,
            'risk_profile' => $riskProfile,
            'projected_range_90' => $range,
        ];
    }

    /**
     * Compute per-position CV thresholds from prior seasons (33rd/66th percentiles).
     * Falls back to sensible defaults if insufficient data.
     *
     * @return array<string, array{safe_upper: float, balanced_upper: float}>
     */
    private function getPositionCvThresholds(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $defaults = [
            'QB' => ['safe_upper' => 0.18, 'balanced_upper' => 0.32],
            'RB' => ['safe_upper' => 0.24, 'balanced_upper' => 0.42],
            'WR' => ['safe_upper' => 0.28, 'balanced_upper' => 0.50],
            'TE' => ['safe_upper' => 0.30, 'balanced_upper' => 0.52],
            'K' => ['safe_upper' => 0.20, 'balanced_upper' => 0.38],
            'DEF' => ['safe_upper' => 0.24, 'balanced_upper' => 0.44],
        ];

        // Try to leverage cached season summaries which include CV
        $rows = \Illuminate\Support\Facades\DB::table('player_season_summaries')
            ->join('players', 'player_season_summaries.player_id', '=', 'players.player_id')
            ->where('player_season_summaries.season', '>=', 2023)
            ->select(['players.position', 'player_season_summaries.volatility'])
            ->limit(5000)
            ->get();

        $cvByPos = [];
        foreach ($rows as $row) {
            $pos = $row->position ?? null;
            if (! $pos) {
                continue;
            }
            $vol = is_string($row->volatility) ? json_decode($row->volatility, true) : (array) $row->volatility;
            $cv = $vol['coefficient_of_variation'] ?? null;
            if (is_numeric($cv) && $cv >= 0) {
                $cvByPos[$pos][] = (float) $cv;
            }
        }

        $thresholds = [];
        foreach ($cvByPos as $pos => $values) {
            if (count($values) < 10) {
                continue;
            }
            sort($values);
            $p33 = $this->percentile($values, 33);
            $p66 = $this->percentile($values, 66);
            $thresholds[$pos] = [
                'safe_upper' => round($p33, 3),
                'balanced_upper' => round(max($p33, $p66), 3),
            ];
        }

        // Merge with defaults where missing
        $cache = array_merge($defaults, $thresholds);

        return $cache;
    }

    private function percentile(array $sortedValues, int $percent): float
    {
        $count = count($sortedValues);
        if ($count === 0) {
            return 0.0;
        }
        $index = ($percent / 100) * ($count - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        if ($lower === $upper) {
            return (float) $sortedValues[$lower];
        }
        $weight = $index - $lower;

        return (float) ($sortedValues[$lower] * (1 - $weight) + $sortedValues[$upper] * $weight);
    }

    private function calculateProjectedTotal(array $team): float
    {
        $total = 0.0;

        // Only count starters for the projected total
        $starters = $team['starters'] ?? [];

        foreach ($starters as $player) {
            if (! is_array($player)) {
                continue;
            }

            // Use actual points if available, otherwise use projected points
            if (isset($player['stats']['stats']['pts_ppr']) && $player['stats']['stats']['pts_ppr'] !== null) {
                $total += (float) $player['stats']['stats']['pts_ppr'];
            } elseif (isset($player['projection']) && $player['projection']) {
                // Try flattened column first, then JSON stats
                $projection = $player['projection'];
                if (isset($projection['pts_ppr']) && $projection['pts_ppr'] !== null) {
                    $total += (float) $projection['pts_ppr'];
                } elseif (isset($projection['stats']['pts_ppr']) && $projection['stats']['pts_ppr'] !== null) {
                    $total += (float) $projection['stats']['pts_ppr'];
                }
            }
        }

        return $total;
    }

    private function getProjectionStandardDeviation(): float
    {
        // Calculate historical projection accuracy from past seasons.
        // Be robust to different DB schemas (JSON stats vs flattened columns).
        $schema = \Illuminate\Support\Facades\Schema::getConnection();
        $hasProjStats = \Illuminate\Support\Facades\Schema::hasColumn('player_projections', 'stats');
        $hasProjPts = \Illuminate\Support\Facades\Schema::hasColumn('player_projections', 'pts_ppr');
        $hasActStats = \Illuminate\Support\Facades\Schema::hasColumn('player_stats', 'stats');
        $hasActPts = \Illuminate\Support\Facades\Schema::hasColumn('player_stats', 'pts_ppr');

        $query = \Illuminate\Support\Facades\DB::table('player_projections')
            ->join('player_stats', function ($join) {
                $join->on('player_projections.player_id', '=', 'player_stats.player_id')
                    ->on('player_projections.season', '=', 'player_stats.season')
                    ->on('player_projections.week', '=', 'player_stats.week');
            })
            ->where('player_projections.season', '>=', 2023)
            ->limit(2000);

        $selects = [
            'player_projections.player_id',
            'player_projections.season',
            'player_projections.week',
        ];
        if ($hasProjStats) {
            $selects[] = 'player_projections.stats as proj_stats';
        }
        if ($hasProjPts) {
            $selects[] = 'player_projections.pts_ppr as proj_pts_ppr';
        }
        if ($hasActStats) {
            $selects[] = 'player_stats.stats as act_stats';
        }
        if ($hasActPts) {
            $selects[] = 'player_stats.pts_ppr as act_pts_ppr';
        }

        $rows = $query->select($selects)->get();

        $squaredErrors = [];
        foreach ($rows as $row) {
            $projected = null;
            if ($hasProjStats && isset($row->proj_stats)) {
                $decoded = is_string($row->proj_stats) ? json_decode($row->proj_stats, true) : (array) $row->proj_stats;
                if (is_array($decoded) && isset($decoded['pts_ppr']) && is_numeric($decoded['pts_ppr'])) {
                    $projected = (float) $decoded['pts_ppr'];
                }
            }
            if ($projected === null && $hasProjPts && isset($row->proj_pts_ppr) && is_numeric($row->proj_pts_ppr)) {
                $projected = (float) $row->proj_pts_ppr;
            }

            $actual = null;
            if ($hasActStats && isset($row->act_stats)) {
                $decodedA = is_string($row->act_stats) ? json_decode($row->act_stats, true) : (array) $row->act_stats;
                if (is_array($decodedA) && isset($decodedA['pts_ppr']) && is_numeric($decodedA['pts_ppr'])) {
                    $actual = (float) $decodedA['pts_ppr'];
                }
            }
            if ($actual === null && $hasActPts && isset($row->act_pts_ppr) && is_numeric($row->act_pts_ppr)) {
                $actual = (float) $row->act_pts_ppr;
            }

            if ($projected !== null && $actual !== null) {
                $diff = (float) $actual - (float) $projected;
                $squaredErrors[] = $diff * $diff;
            }
        }

        if (empty($squaredErrors)) {
            // Fallback to a reasonable default based on typical fantasy football projection accuracy
            return 8.0; // ~8 points standard deviation is typical
        }

        $mse = collect($squaredErrors)->avg();

        return sqrt($mse);
    }

    private function calculateTeamConfidenceInterval(array $team, float $projectionStdDev): array
    {
        $starters = $team['starters'] ?? [];
        $projectedTotal = 0.0;
        $varianceSum = 0.0;

        foreach ($starters as $player) {
            if (! is_array($player)) {
                continue;
            }

            $projectedPoints = 0.0;

            // Use actual points if available, otherwise use projected points
            if (isset($player['stats']['stats']['pts_ppr']) && $player['stats']['stats']['pts_ppr'] !== null) {
                $projectedPoints = (float) $player['stats']['stats']['pts_ppr'];
                // Actual points have no uncertainty (game already played)
                // Add to total and DO NOT add variance
            } elseif (isset($player['projection']) && $player['projection']) {
                // Try flattened column first, then JSON stats
                $projection = $player['projection'];
                if (isset($projection['pts_ppr']) && $projection['pts_ppr'] !== null) {
                    $projectedPoints = (float) $projection['pts_ppr'];
                } elseif (isset($projection['stats']['pts_ppr']) && $projection['stats']['pts_ppr'] !== null) {
                    $projectedPoints = (float) $projection['stats']['pts_ppr'];
                } else {
                    $projectedPoints = 0;
                }
            } else {
                $projectedPoints = 0;
            }

            $projectedTotal += $projectedPoints;

            // Add variance only when using projected value (not actual)
            $hasActual = isset($player['stats']['stats']['pts_ppr']) && $player['stats']['stats']['pts_ppr'] !== null;
            if (! $hasActual && $projectedPoints > 0) {
                $varianceSum += pow($projectionStdDev, 2);
            }
        }

        $teamStdDev = sqrt($varianceSum);

        // 90% confidence interval uses ~1.645 standard deviations
        $confidenceZ = 1.645;
        $marginOfError = $confidenceZ * $teamStdDev;

        return [
            'projected' => round($projectedTotal, 1),
            'lower_90' => round(max(0, $projectedTotal - $marginOfError), 1),
            'upper_90' => round($projectedTotal + $marginOfError, 1),
            'confidence_range' => round($marginOfError * 2, 1),
            'stddev' => round($teamStdDev, 3),
        ];
    }

    /**
     * Build a normal distribution (mean/variance) for a team's total points.
     * Actual points contribute mean without variance; projections add mean and variance.
     *
     * @return array{mean: float, variance: float}
     */
    private function computeTeamDistribution(array $team, float $projectionStdDev): array
    {
        $starters = $team['starters'] ?? [];
        $mean = 0.0;
        $variance = 0.0;

        foreach ($starters as $player) {
            if (! is_array($player)) {
                continue;
            }

            $actual = $player['stats']['stats']['pts_ppr'] ?? null;
            if ($actual !== null) {
                $mean += (float) $actual;

                // no variance for completed outcomes
                continue;
            }

            $proj = null;
            if (isset($player['projection']['pts_ppr']) && $player['projection']['pts_ppr'] !== null) {
                $proj = (float) $player['projection']['pts_ppr'];
            } elseif (isset($player['projection']['stats']['pts_ppr']) && $player['projection']['stats']['pts_ppr'] !== null) {
                $proj = (float) $player['projection']['stats']['pts_ppr'];
            }

            if ($proj !== null) {
                $mean += $proj;
                $variance += pow($projectionStdDev, 2);
            }
        }

        return [
            'mean' => $mean,
            'variance' => $variance,
        ];
    }

    // Legacy helper removed in favor of ComputeWinProbability
}
