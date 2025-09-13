<?php

namespace App\Actions\Matchups;

use App\Models\Player;
use App\Models\PlayerProjections;
use Illuminate\Support\Collection;

class OptimizeLineup
{
    public function __construct(
        private CalculatePlayerVolatility $volatility_calculator,
        private DetermineRosterSlots $slot_determiner,
        private SelectOptimalLineup $lineup_selector
    ) {}

    /**
     * Find the optimal lineup to maximize win probability.
     *
     * @param  array<int,string>  $current_starters
     * @param  array<int,string>  $bench_players
     * @param  array<string,array{actual: float, projected: float, used: float, status: string}>  $current_points
     * @param  array<int,string>|null  $roster_positions
     * @return array{
     *     optimized_lineup: array{starters: array<int,string>, bench: array<int,string>, improvement: float, total_projected: float},
     *     recommendations: array<string,array{player_id: string, position: string|null, projection: float, confidence_score: float, volatility: array<string,float|int>}>,
     *     risk: array{level: string, average_confidence: float}
     * }
     */
    public function execute(
        array $current_starters,
        array $bench_players,
        array $current_points,
        int $season,
        int $week,
        ?array $roster_positions = null
    ): array {
        // Get all available players
        $all_player_ids = array_unique(array_merge($current_starters, $bench_players));

        if (empty($all_player_ids)) {
            return $this->emptyResult();
        }

        // Load player data
        $players = $this->loadPlayers($all_player_ids);
        $projections = $this->loadProjections($all_player_ids, $season, $week);

        // Build candidate pool with scoring data
        $candidates = $this->buildCandidatePool($all_player_ids, $players, $projections, $current_points);

        // Determine roster slots to fill
        $roster_slots = $this->slot_determiner->execute($current_starters, $players, $roster_positions);

        // Select optimal lineup
        $optimal_result = $this->lineup_selector->execute($candidates, $roster_slots);

        // Calculate results
        $optimized_starters = $optimal_result['starters'];
        $optimized_bench = array_values(array_diff($all_player_ids, $optimized_starters));

        $current_total = $this->calculateTotal($current_starters, $current_points);
        $optimized_total = $this->calculateTotal($optimized_starters, $current_points, $candidates);
        $improvement = round($optimized_total - $current_total, 1);

        // Build recommendations (only new starters)
        $recommendations = $this->buildRecommendations($current_starters, $optimized_starters, $candidates);

        // Assess risk
        $risk = $this->assessRisk($candidates, $optimized_starters);

        return [
            'optimized_lineup' => [
                'starters' => $optimized_starters,
                'bench' => $optimized_bench,
                'improvement' => $improvement,
                'total_projected' => round($optimized_total, 1),
            ],
            'recommendations' => $recommendations,
            'risk' => $risk,
        ];
    }

    /**
     * Load players from database.
     */
    private function loadPlayers(array $player_ids): Collection
    {
        return Player::query()
            ->whereIn('player_id', $player_ids)
            ->get()
            ->keyBy('player_id');
    }

    /**
     * Load projections for the specified week.
     */
    private function loadProjections(array $player_ids, int $season, int $week): Collection
    {
        return PlayerProjections::query()
            ->whereIn('player_id', $player_ids)
            ->where('season', $season)
            ->where('week', $week)
            ->get()
            ->keyBy('player_id');
    }

    /**
     * Build candidate pool with all necessary scoring data.
     */
    private function buildCandidatePool(
        array $player_ids,
        Collection $players,
        Collection $projections,
        array $current_points
    ): Collection {
        return collect($player_ids)
            ->mapWithKeys(function (string $player_id) use ($players, $projections, $current_points) {
                $player = $players->get($player_id);
                if (! $player) {
                    return [];
                }

                // Get projected points (use current points if available, otherwise projections)
                $projected_points = $this->getProjectedPoints($player_id, $projections, $current_points);

                // Calculate volatility and confidence
                $volatility = $this->volatility_calculator->execute($player);
                $confidence = $this->calculateConfidence($volatility);

                return [
                    $player_id => [
                        'player_id' => $player_id,
                        'position' => $player->position,
                        'projected_points' => $projected_points,
                        'confidence' => $confidence,
                        'volatility' => $volatility,
                    ],
                ];
            });
    }

    /**
     * Get projected points for a player.
     */
    private function getProjectedPoints(string $player_id, Collection $projections, array $current_points): float
    {
        // Use current points if available
        if (isset($current_points[$player_id]['used'])) {
            return (float) $current_points[$player_id]['used'];
        }

        // Otherwise use projections
        $projection = $projections->get($player_id);
        if ($projection) {
            return (float) ($projection->pts_ppr ?? ($projection->stats['pts_ppr'] ?? 0.0));
        }

        return 0.0;
    }

    /**
     * Calculate confidence score from volatility (lower volatility = higher confidence).
     */
    private function calculateConfidence(array $volatility): float
    {
        $coefficient_of_variation = (float) ($volatility['coefficient_of_variation'] ?? 1.0);

        return max(0.0, min(1.0, 1.0 - min(0.9, $coefficient_of_variation)));
    }

    /**
     * Calculate total points for a lineup.
     */
    private function calculateTotal(array $starters, array $current_points, ?Collection $candidates = null): float
    {
        return collect($starters)->reduce(function (float $total, string $player_id) use ($current_points, $candidates) {
            // Use current points if available
            if (isset($current_points[$player_id]['used'])) {
                return $total + (float) $current_points[$player_id]['used'];
            }

            // Otherwise use candidate projected points
            if ($candidates && $candidates->has($player_id)) {
                return $total + (float) $candidates->get($player_id)['projected_points'];
            }

            return $total;
        }, 0.0);
    }

    /**
     * Build recommendations for new starters only.
     */
    private function buildRecommendations(array $current_starters, array $optimized_starters, Collection $candidates): array
    {
        return collect($optimized_starters)
            ->reject(fn (string $player_id) => in_array($player_id, $current_starters, true))
            ->mapWithKeys(function (string $player_id) use ($candidates) {
                $candidate = $candidates->get($player_id);
                if (! $candidate) {
                    return [];
                }

                return [
                    $player_id => [
                        'player_id' => $player_id,
                        'position' => $candidate['position'],
                        'projection' => $candidate['projected_points'],
                        'confidence_score' => $candidate['confidence'],
                        'volatility' => $candidate['volatility'],
                    ],
                ];
            })
            ->all();
    }

    /**
     * Assess overall lineup risk.
     */
    private function assessRisk(Collection $candidates, array $optimized_starters): array
    {
        $starter_candidates = collect($optimized_starters)
            ->map(fn ($player_id) => $candidates->get($player_id))
            ->filter();

        $avg_confidence = $starter_candidates->pluck('confidence')->avg() ?? 0.0;
        $avg_cv = $starter_candidates->pluck('volatility.coefficient_of_variation')->avg() ?? 1.0;

        $level = match (true) {
            $avg_cv < 0.35 => 'low',
            $avg_cv < 0.6 => 'medium',
            default => 'high',
        };

        return [
            'level' => $level,
            'average_confidence' => round((float) $avg_confidence, 3),
        ];
    }

    /**
     * Return empty result structure.
     */
    private function emptyResult(): array
    {
        return [
            'optimized_lineup' => [
                'starters' => [],
                'bench' => [],
                'improvement' => 0.0,
                'total_projected' => 0.0,
            ],
            'recommendations' => [],
            'risk' => [
                'level' => 'low',
                'average_confidence' => 0.0,
            ],
        ];
    }
}
