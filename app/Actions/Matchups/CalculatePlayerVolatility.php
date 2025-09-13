<?php

namespace App\Actions\Matchups;

use App\Models\Player;
use App\Models\PlayerStats;

class CalculatePlayerVolatility
{
    /**
     * Calculate volatility metrics for a player based on recent performance.
     * Returns standard deviation, mean, and coefficient of variation.
     *
     * @return array{std_dev: float, mean: float, coefficient_of_variation: float, games_analyzed: int}
     */
    public function execute(Player $player): array
    {
        $recent_stats = PlayerStats::query()
            ->where('player_id', $player->player_id)
            ->orderBy('season', 'desc')
            ->orderBy('week', 'desc')
            ->limit(16) // Last 16 games for meaningful sample
            ->get();

        $points = $recent_stats
            ->map(fn ($stat) => $stat->stats['pts_ppr'] ?? null)
            ->filter()
            ->map(fn ($pts) => (float) $pts)
            ->values();

        $games_count = $points->count();

        if ($games_count === 0) {
            return [
                'std_dev' => 6.0, // Default volatility for unknown players
                'mean' => 0.0,
                'coefficient_of_variation' => 1.0,
                'games_analyzed' => 0,
            ];
        }

        $mean = $points->avg();
        $variance = $points->reduce(function ($carry, $point) use ($mean) {
            return $carry + (($point - $mean) ** 2);
        }, 0.0) / max(1, $games_count - 1);

        $std_dev = sqrt($variance);
        $coefficient_of_variation = $mean > 0.0 ? $std_dev / $mean : 1.0;

        return [
            'std_dev' => round($std_dev, 3),
            'mean' => round($mean, 3),
            'coefficient_of_variation' => round($coefficient_of_variation, 3),
            'games_analyzed' => $games_count,
        ];
    }
}
