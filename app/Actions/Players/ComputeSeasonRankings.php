<?php

namespace App\Actions\Players;

use App\Models\Player;

class ComputeSeasonRankings
{
    /**
     * Build a lookup of player_id => season position rank for the given season.
     * Uses aggregated actual PPR points from weekly stats for that season.
     *
     * @return array<int|string, int> player_id => rank
     */
    public function handle(int $season): array
    {
        $players = Player::query()
            ->active()
            ->playablePositions()
            ->get();

        if ($players->isEmpty()) {
            return [];
        }

        $playersWithPoints = [];

        foreach ($players as $player) {
            // Aggregate total PPR points for the season using dynamic helpers
            $totals = $player->calculateSeasonStatTotals($season);

            $totalPoints = isset($totals['pts_ppr']) && is_numeric($totals['pts_ppr'])
                ? (float) $totals['pts_ppr']
                : 0.0;

            if ($totalPoints > 0) {
                $playersWithPoints[] = [
                    'player_id' => $player->player_id,
                    'position' => $player->position,
                    'total_points' => $totalPoints,
                ];
            }
        }

        if (empty($playersWithPoints)) {
            return [];
        }

        // Group by position and rank within each group by total_points desc
        $byPosition = collect($playersWithPoints)->groupBy('position');

        $lookup = [];
        foreach ($byPosition as $position => $positionPlayers) {
            $sorted = $positionPlayers->sortByDesc('total_points')->values();
            $rank = 1;
            foreach ($sorted as $playerData) {
                $lookup[$playerData['player_id']] = $rank;
                $rank++;
            }
        }

        return $lookup;
    }
}
