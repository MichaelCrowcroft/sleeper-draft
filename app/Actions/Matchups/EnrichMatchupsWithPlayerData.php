<?php

namespace App\Actions\Matchups;

use App\Models\Player;
use App\Models\PlayerProjections;
use Illuminate\Support\Collection;

class EnrichMatchupsWithPlayerData
{
    public function execute(array $matchups, int $season, int $week, bool $compact = true): array
    {
        if (empty($matchups)) {
            return [];
        }

        $playerIds = collect($matchups)
            ->flatten(1)
            ->flatMap(function ($matchup) {
                $ids = [];
                $ids = array_merge($ids, $matchup['starters'] ?? []);
                $ids = array_merge($ids, $matchup['players'] ?? []);

                return $ids;
            })
            ->filter()
            ->unique()
            ->values();

        if ($playerIds->isEmpty()) {
            return $matchups;
        }

        $players = Player::whereIn('player_id', $playerIds)
            ->with([
                'projections' => function ($query) use ($season, $week) {
                    $query->where('season', $season)->where('week', $week);
                },
                'stats' => function ($query) use ($season, $week) {
                    $query->where('season', $season)->where('week', $week);
                },
            ])
            ->get()
            ->keyBy('player_id');

        $projectionStdDev = $this->getProjectionStandardDeviation();

        foreach ($matchups as &$matchup) {
            foreach ($matchup as &$team) {
                $team = $this->enrichTeam($team, $players, $compact, $projectionStdDev);
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

    private function enrichTeam(array $team, Collection $players, bool $compact = true, ?float $projectionStdDev = null): array
    {
        return collect($team)
            ->transform(function ($value, $key) use ($players, $compact, $projectionStdDev) {
                if ($key === 'starters' || $key === 'players') {
                    return collect($value)
                        ->map(fn ($player_id) => $this->enrichPlayer($player_id, $players, $compact, $projectionStdDev))
                        ->all();
                }

                return $value;
            })
            ->all();
    }

    private function enrichPlayer(string $player_id, Collection $players, bool $compact = true, ?float $projectionStdDev = null): array
    {
        $player = $players->get($player_id);

        $projection = $player->projections->first();
        $stats = $player->stats->first();

        $baseData = [
            'player_id' => $player->player_id,
            'name' => $player->full_name ?? ($player->first_name.' '.$player->last_name),
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'position' => $player->position,
            'team' => $player->team,
            'fantasy_positions' => $player->fantasy_positions,
            'active' => $player->active,
            'injury_status' => $player->injury_status,
        ];

        if ($compact) {
            // Return minimal data for matchups to avoid truncation
            $projectedPoints = null;
            if ($stats && isset($stats->stats['pts_ppr'])) {
                $projectedPoints = (float) $stats->stats['pts_ppr'];
            } elseif ($projection) {
                if (isset($projection->pts_ppr)) {
                    $projectedPoints = (float) $projection->pts_ppr;
                } elseif (isset($projection->stats['pts_ppr'])) {
                    $projectedPoints = (float) $projection->stats['pts_ppr'];
                }
            }

            // Add volatility/range information if we have projected points and std dev
            $playerData = [
                'projected_points' => $projectedPoints,
                'is_starter' => true, // This will be overridden by MergeEnrichedMatchupsWithRosterPositions
            ];

            if ($projectedPoints !== null && $projectionStdDev !== null) {
                $confidenceZ = 1.645; // 90% confidence interval
                $marginOfError = $confidenceZ * $projectionStdDev;
                $playerData['projected_range_low'] = round(max(0, $projectedPoints - $marginOfError), 1);
                $playerData['projected_range_high'] = round($projectedPoints + $marginOfError, 1);
                $playerData['projection_std_dev'] = round($projectionStdDev, 2);
            }

            return array_merge($baseData, $playerData);
        }

        // Return full data when not in compact mode
        return array_merge($baseData, [
            'projection' => $projection,
            'stats' => $stats,
            'is_starter' => true, // This will be overridden by MergeEnrichedMatchupsWithRosterPositions
        ]);
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

            // Use projected_points field (works for both compact and full modes)
            if (isset($player['projected_points']) && $player['projected_points'] !== null) {
                $total += (float) $player['projected_points'];
            }
        }

        return $total;
    }

    private function getProjectionStandardDeviation(): float
    {
        // Calculate historical projection accuracy from past seasons
        // Use current season and past seasons to estimate projection error
        $projections = PlayerProjections::with('player')
            ->join('player_stats', function ($join) {
                $join->on('player_projections.player_id', '=', 'player_stats.player_id')
                    ->on('player_projections.season', '=', 'player_stats.season')
                    ->on('player_projections.week', '=', 'player_stats.week');
            })
            ->where('player_projections.season', '>=', 2023)
            ->whereNotNull('player_projections.stats->pts_ppr')
            ->whereNotNull('player_stats.stats->pts_ppr')
            ->select([
                'player_projections.stats',
                'player_stats.stats as actual_stats',
            ])
            ->limit(1000) // Limit to avoid memory issues
            ->get();

        $squaredErrors = [];
        foreach ($projections as $projection) {
            $projected = $projection->stats['pts_ppr'] ?? null;
            $actual = $projection->actual_stats['pts_ppr'] ?? null;

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
            $hasActual = false;

            // Check if this is actual stats (completed game) vs projection
            // In compact mode, we need to determine if the projected_points came from actual stats
            if (isset($player['projected_points']) && $player['projected_points'] !== null) {
                $projectedPoints = (float) $player['projected_points'];
                // We can't easily determine if this came from actual stats in compact mode,
                // so we'll assume projections have uncertainty. This is a limitation of compact mode.
                $hasActual = false;
            }

            $projectedTotal += $projectedPoints;

            // Add variance for projected values (not actual completed games)
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

            // In compact mode, we treat all projected_points as projections with uncertainty
            $proj = $player['projected_points'] ?? null;
            if ($proj !== null) {
                $mean += (float) $proj;
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
