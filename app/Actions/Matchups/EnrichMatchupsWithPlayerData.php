<?php

namespace App\Actions\Matchups;

use App\Models\Player;
use Illuminate\Support\Collection;
use App\Models\PlayerProjections;

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

        $projectionStdDev = $this->getProjectionStandardDeviation();

        foreach($matchups as &$matchup) {
            $teams = array_values($matchup);
            foreach ($matchup as &$team) {
                $team = $this->enrichTeam($team, $players);
                $team['projected_total'] = $this->calculateProjectedTotal($team);
                $team['confidence_interval'] = $this->calculateTeamConfidenceInterval($team, $projectionStdDev);
            }

            $teamA = $teams[0];
            $teamB = $teams[1];

            $matchup['win_probabilities'] = [
                'team_a_win_probability' => $this->calculateWinProbability($teamA, $teamB, $projectionStdDev),
                'team_b_win_probability' => $this->calculateWinProbability($teamB, $teamA, $projectionStdDev),
            ];
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
                'player_stats.stats as actual_stats'
            ])
            ->limit(1000) // Limit to avoid memory issues
            ->get();

        $errors = [];
        foreach ($projections as $projection) {
            $projected = $projection->stats['pts_ppr'] ?? null;
            $actual = $projection->actual_stats['pts_ppr'] ?? null;

            if ($projected !== null && $actual !== null) {
                $errors[] = abs($actual - $projected);
            }
        }

        if (empty($errors)) {
            // Fallback to a reasonable default based on typical fantasy football projection accuracy
            return 8.0; // ~8 points standard deviation is typical
        }

        return collect($errors)->avg();
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

        $scoreA = $confidenceA['projected'];
        $scoreB = $confidenceB['projected'];

        // Simple case: if scores are very close, 50/50 chance
        if (abs($scoreA - $scoreB) < 1.0) {
            return 50.0;
        }

        // Calculate win probability using logistic function
        // Higher score = higher probability, with some uncertainty
        $scoreDiff = $scoreA - $scoreB;

        // Scale the difference to create reasonable probabilities
        // A 10-point difference should be about 75% vs 25%, not 100% vs 0%
        $zScore = $scoreDiff / 8.0; // Assume ~8 points standard deviation

        // Logistic function for probability
        $probability = 1 / (1 + exp(-$zScore));

        return round($probability * 100, 1);
    }
}
