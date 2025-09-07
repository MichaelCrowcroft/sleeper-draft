<?php

namespace App\Actions\Matchups;

use App\Models\Player;
use Illuminate\Support\Collection;

class OptimizeLineup
{
    /**
     * Optimize a lineup by considering player volatility and bench alternatives.
     *
     * @param array $currentStarters Array of player IDs currently starting
     * @param array $benchPlayers Array of player IDs on the bench
     * @param array $currentPoints Current points data from ComputePlayerWeekPoints
     * @param int $season Current season
     * @param int $week Current week
     * @return array Optimization results with recommendations
     */
    public function execute(
        array $currentStarters,
        array $benchPlayers,
        array $currentPoints,
        int $season,
        int $week
    ): array {
        // Get all players (starters + bench) with their data
        $allPlayerIds = array_unique(array_merge($currentStarters, $benchPlayers));

        $players = Player::query()
            ->whereIn('player_id', $allPlayerIds)
            ->with([
                'stats' => fn ($q) => $q->where('season', $season)->orderBy('week'),
                'projections' => fn ($q) => $q->where('season', $season)->where('week', $week),
                'stats2024' => fn ($q) => $q->orderBy('week'), // Previous season for volatility
            ])
            ->get()
            ->keyBy('player_id');

        // Analyze player volatility and project performance
        $playerAnalysis = $this->analyzePlayers($players, $currentPoints, $season, $week);

        // Group players by position for optimization
        $positionGroups = $this->groupPlayersByPosition($playerAnalysis, $currentStarters, $benchPlayers);

        // Generate optimization recommendations
        $recommendations = $this->generateRecommendations($positionGroups, $currentStarters);

        // Calculate projected improvements
        $currentTotal = array_sum(array_column($currentPoints, 'used'));
        $optimizedTotal = $this->calculateOptimizedTotal($recommendations, $playerAnalysis);

        return [
            'current_lineup' => [
                'starters' => $currentStarters,
                'total_points' => round($currentTotal, 1),
            ],
            'optimized_lineup' => [
                'starters' => array_keys($recommendations),
                'total_points' => round($optimizedTotal, 1),
                'improvement' => round($optimizedTotal - $currentTotal, 1),
            ],
            'recommendations' => $recommendations,
            'player_analysis' => $playerAnalysis,
            'risk_assessment' => $this->assessRisk($recommendations, $playerAnalysis),
        ];
    }

    /**
     * Analyze players to calculate volatility and projected performance.
     */
    private function analyzePlayers(Collection $players, array $currentPoints, int $season, int $week): array
    {
        $analysis = [];

        foreach ($players as $playerId => $player) {
            $currentData = $currentPoints[$playerId] ?? ['used' => 0.0, 'status' => 'upcoming'];

            // Calculate volatility from historical performance
            $volatility = $this->calculateVolatility($player);

            // Get projection data
            $projection = $player->projections->first();
            $projectedPoints = $this->extractProjectedPoints($projection);

            // Calculate confidence score (lower volatility = higher confidence)
            $confidence = $this->calculateConfidenceScore($volatility, $projectedPoints);

            $analysis[$playerId] = [
                'player_id' => $playerId,
                'name' => $player->full_name ?: ($player->first_name . ' ' . $player->last_name),
                'position' => $player->position,
                'current_points' => $currentData['used'],
                'projected_points' => $projectedPoints,
                'volatility' => $volatility,
                'confidence_score' => $confidence,
                'status' => $currentData['status'],
                'ceiling' => $projectedPoints + ($volatility['std_dev'] * 1.5),
                'floor' => max(0, $projectedPoints - ($volatility['std_dev'] * 1.5)),
            ];
        }

        return $analysis;
    }

    /**
     * Calculate player volatility based on historical performance.
     */
    private function calculateVolatility(Player $player): array
    {
        $historicalPoints = [];

        // Use 2024 season stats if available, otherwise current season
        $stats = $player->relationLoaded('stats2024')
            ? $player->getRelation('stats2024')
            : $player->stats()->where('season', 2024)->get();

        foreach ($stats as $weeklyStat) {
            if (isset($weeklyStat->stats['pts_ppr']) && is_numeric($weeklyStat->stats['pts_ppr'])) {
                $points = (float) $weeklyStat->stats['pts_ppr'];
                if ($points > 0) { // Only count games where player was active
                    $historicalPoints[] = $points;
                }
            }
        }

        if (empty($historicalPoints)) {
            return [
                'std_dev' => 6.0, // Default volatility
                'coefficient_of_variation' => 0.5,
                'games_analyzed' => 0,
            ];
        }

        $mean = array_sum($historicalPoints) / count($historicalPoints);
        $variance = 0.0;

        foreach ($historicalPoints as $points) {
            $variance += pow($points - $mean, 2);
        }

        $variance = $variance / count($historicalPoints);
        $stdDev = sqrt($variance);
        $coefficientOfVariation = $mean > 0 ? $stdDev / $mean : 1.0;

        return [
            'std_dev' => round($stdDev, 2),
            'coefficient_of_variation' => round($coefficientOfVariation, 3),
            'games_analyzed' => count($historicalPoints),
        ];
    }

    /**
     * Extract projected points from projection data.
     */
    private function extractProjectedPoints(?object $projection): float
    {
        if (!$projection) {
            return 0.0;
        }

        $stats = is_array($projection->stats) ? $projection->stats : [];

        if (isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr'])) {
            return (float) $stats['pts_ppr'];
        }

        if (isset($projection->pts_ppr) && is_numeric($projection->pts_ppr)) {
            return (float) $projection->pts_ppr;
        }

        return 0.0;
    }

    /**
     * Calculate confidence score based on volatility and projection strength.
     */
    private function calculateConfidenceScore(array $volatility, float $projectedPoints): float
    {
        if ($volatility['games_analyzed'] === 0) {
            return 0.5; // Neutral confidence for new/unknown players
        }

        // Lower coefficient of variation = higher confidence
        $volatilityScore = max(0, 1 - $volatility['coefficient_of_variation']);

        // Higher projected points = higher confidence (up to a point)
        $strengthScore = min(1.0, $projectedPoints / 25.0);

        // Weighted average
        return round(($volatilityScore * 0.6) + ($strengthScore * 0.4), 3);
    }

    /**
     * Group players by position for optimization.
     */
    private function groupPlayersByPosition(array $playerAnalysis, array $currentStarters, array $benchPlayers): array
    {
        $groups = [];

        foreach ($playerAnalysis as $playerId => $analysis) {
            $position = $analysis['position'];
            $isStarter = in_array($playerId, $currentStarters);
            $isBench = in_array($playerId, $benchPlayers);

            if (!isset($groups[$position])) {
                $groups[$position] = [
                    'starters' => [],
                    'bench' => [],
                ];
            }

            if ($isStarter) {
                $groups[$position]['starters'][] = $analysis;
            } elseif ($isBench) {
                $groups[$position]['bench'][] = $analysis;
            }
        }

        return $groups;
    }

    /**
     * Generate optimization recommendations for each position.
     */
    private function generateRecommendations(array $positionGroups, array $currentStarters): array
    {
        $recommendations = [];

        foreach ($positionGroups as $position => $group) {
            $allCandidates = array_merge($group['starters'], $group['bench']);

            // Sort by projected points (primary) and confidence (secondary)
            usort($allCandidates, function ($a, $b) {
                $pointsDiff = $b['projected_points'] - $a['projected_points'];
                if (abs($pointsDiff) > 0.1) {
                    return $pointsDiff > 0 ? 1 : -1;
                }
                return $b['confidence_score'] <=> $a['confidence_score'];
            });

            // Take the best player for this position
            if (!empty($allCandidates)) {
                $bestPlayer = $allCandidates[0];
                $recommendations[$bestPlayer['player_id']] = [
                    'player_id' => $bestPlayer['player_id'],
                    'name' => $bestPlayer['name'],
                    'position' => $position,
                    'current_points' => $bestPlayer['current_points'],
                    'projected_points' => $bestPlayer['projected_points'],
                    'confidence_score' => $bestPlayer['confidence_score'],
                    'volatility' => $bestPlayer['volatility'],
                    'is_current_starter' => in_array($bestPlayer['player_id'], $currentStarters),
                    'improvement_potential' => round($bestPlayer['projected_points'] - $bestPlayer['current_points'], 1),
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Calculate total points for optimized lineup.
     */
    private function calculateOptimizedTotal(array $recommendations, array $playerAnalysis): float
    {
        $total = 0.0;

        foreach ($recommendations as $playerId => $rec) {
            $total += $rec['projected_points'];
        }

        return $total;
    }

    /**
     * Assess overall risk of the optimized lineup.
     */
    private function assessRisk(array $recommendations, array $playerAnalysis): array
    {
        if (empty($recommendations)) {
            return [
                'level' => 'unknown',
                'description' => 'No recommendations available',
            ];
        }

        $totalVolatility = 0.0;
        $totalConfidence = 0.0;
        $highVolatilityCount = 0;

        foreach ($recommendations as $playerId => $rec) {
            $volatility = $playerAnalysis[$playerId]['volatility'];
            $totalVolatility += $volatility['coefficient_of_variation'];
            $totalConfidence += $rec['confidence_score'];

            if ($volatility['coefficient_of_variation'] > 0.6) {
                $highVolatilityCount++;
            }
        }

        $avgVolatility = $totalVolatility / count($recommendations);
        $avgConfidence = $totalConfidence / count($recommendations);

        if ($avgConfidence > 0.7 && $highVolatilityCount === 0) {
            $level = 'low';
            $description = 'Low risk - high confidence players with stable projections';
        } elseif ($avgConfidence > 0.5 && $highVolatilityCount <= 2) {
            $level = 'medium';
            $description = 'Medium risk - mix of reliable and volatile players';
        } else {
            $level = 'high';
            $description = 'High risk - several volatile players or low confidence projections';
        }

        return [
            'level' => $level,
            'description' => $description,
            'average_confidence' => round($avgConfidence, 3),
            'average_volatility' => round($avgVolatility, 3),
            'high_volatility_players' => $highVolatilityCount,
        ];
    }
}
