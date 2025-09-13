<?php

namespace App\Actions\Matchups;

use App\Models\Player;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EnrichMatchupsWithPlayerData
{
    public function execute(array $matchups, int $season, int $week): array
    {
        if (empty($matchups)) {
            return [];
        }

        $playerIds = collect($matchups)
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

        // Calculate projection accuracy for confidence intervals
        $projectionStdDev = $this->getProjectionStandardDeviation();

        foreach ($matchups as &$matchup) {
            $teams = array_values($matchup); // Get teams as array for win probability calculation
            foreach ($matchup as &$team) {
                $team = $this->enrichTeam($team, $players);
                $team['projected_total'] = $this->calculateProjectedTotal($team);
                $team['confidence_interval'] = $this->calculateTeamConfidenceInterval($team, $projectionStdDev);
            }

            // Calculate win probabilities between the two teams in this matchup
            if (count($teams) === 2) {
                $teamA = $teams[0];
                $teamB = $teams[1];

                $matchup['win_probabilities'] = [
                    'team_a_win_probability' => $this->calculateWinProbability($teamA, $teamB, $projectionStdDev),
                    'team_b_win_probability' => $this->calculateWinProbability($teamB, $teamA, $projectionStdDev),
                ];
            }
        }

        return $matchups;
    }

    private function enrichTeam(array $team, Collection $players): array
    {
        return collect($team)
            ->transform(function ($value, $key) use ($players) {
                if ($key === 'starters' || $key === 'players') {
                    return collect($value)
                        ->map(fn ($player_id) => $this->enrichPlayer($player_id, $players))
                        ->all();
                }

                return $value;
            })
            ->all();
    }

    private function enrichPlayer(string $player_id, Collection $players): array
    {
        $player = $players->get($player_id);

        $projection = $player->projections->first();
        $stats = $player->stats->first();

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
        ];
    }

    private function calculateProjectedTotal(array $team): float
    {
        $total = 0.0;

        // Only count starters for the projected total
        $starters = $team['starters'] ?? [];

        foreach ($starters as $player) {
            if (!is_array($player)) {
                continue;
            }

            // Use actual points if available, otherwise use projected points
            if (isset($player['stats']['stats']['pts_ppr']) && $player['stats']['stats']['pts_ppr'] !== null) {
                $total += (float) $player['stats']['stats']['pts_ppr'];
            } elseif (isset($player['projection']['stats']['pts_ppr']) && $player['projection']['stats']['pts_ppr'] !== null) {
                $total += (float) $player['projection']['stats']['pts_ppr'];
            }
        }

        return $total;
    }

    private function getProjectionStandardDeviation(): float
    {
        // Calculate historical projection accuracy from past seasons
        // Use current season and past seasons to estimate projection error
        $projectionErrors = DB::table('player_projections as p')
            ->join('player_stats as s', function ($join) {
                $join->on('p.player_id', '=', 's.player_id')
                    ->on('p.season', '=', 's.season')
                    ->on('p.week', '=', 's.week');
            })
            ->where('p.season', '>=', 2023) // Use recent seasons for accuracy
            ->whereNotNull('p.pts_ppr')
            ->whereRaw('JSON_EXTRACT(s.stats, "$.pts_ppr") IS NOT NULL')
            ->selectRaw('ABS(JSON_EXTRACT(s.stats, "$.pts_ppr") - p.pts_ppr) as error')
            ->pluck('error');

        if ($projectionErrors->isEmpty()) {
            // Fallback to a reasonable default based on typical fantasy football projection accuracy
            return 8.0; // ~8 points standard deviation is typical
        }

        return $projectionErrors->avg(); // Use average absolute error as standard deviation estimate
    }

    private function calculateTeamConfidenceInterval(array $team, float $projectionStdDev): array
    {
        $starters = $team['starters'] ?? [];
        $projectedTotal = 0.0;
        $varianceSum = 0.0;

        foreach ($starters as $player) {
            if (!is_array($player)) {
                continue;
            }

            $projectedPoints = 0.0;

            // Use actual points if available, otherwise use projected points
            if (isset($player['stats']['stats']['pts_ppr']) && $player['stats']['stats']['pts_ppr'] !== null) {
                $projectedPoints = (float) $player['stats']['stats']['pts_ppr'];
                // Actual points have no uncertainty (game already played)
                continue;
            } elseif (isset($player['projection']['stats']['pts_ppr']) && $player['projection']['stats']['pts_ppr'] !== null) {
                $projectedPoints = (float) $player['projection']['stats']['pts_ppr'];
            }

            $projectedTotal += $projectedPoints;

            // Add variance for projected players (assuming independent errors)
            if ($projectedPoints > 0) {
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
        ];
    }

    private function calculateWinProbability(array $teamA, array $teamB, float $projectionStdDev): float
    {
        $confidenceA = $this->calculateTeamConfidenceInterval($teamA, $projectionStdDev);
        $confidenceB = $this->calculateTeamConfidenceInterval($teamB, $projectionStdDev);

        $meanA = $confidenceA['projected'];
        $meanB = $confidenceB['projected'];

        // Calculate standard deviations from confidence intervals
        // 90% CI = mean ± 1.645 * σ, so σ = (upper - lower) / (2 * 1.645)
        $stdDevA = $confidenceA['confidence_range'] / (2 * 1.645);
        $stdDevB = $confidenceB['confidence_range'] / (2 * 1.645);

        $combinedStdDev = sqrt(pow($stdDevA, 2) + pow($stdDevB, 2));

        if ($combinedStdDev == 0) {
            // No uncertainty, just compare means
            return $meanA > $meanB ? 100.0 : 0.0;
        }

        // Calculate win probability for team A using normal distribution
        $zScore = ($meanA - $meanB) / $combinedStdDev;

        // Use approximation of normal CDF for win probability
        return round($this->normalCDF($zScore) * 100, 1);
    }

    private function normalCDF(float $z): float
    {
        // Abramowitz & Stegun approximation for normal CDF
        $a1 =  0.254829592;
        $a2 = -0.284496736;
        $a3 =  1.421413741;
        $a4 = -1.453152027;
        $a5 =  1.061405429;
        $p  =  0.3275911;

        $sign = $z < 0 ? -1 : 1;
        $z = abs($z) / sqrt(2.0);

        $t = 1.0 / (1.0 + $p * $z);
        $erf = 1.0 - (((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t) * exp(-$z * $z);

        return 0.5 * (1.0 + $sign * $erf);
    }
}
