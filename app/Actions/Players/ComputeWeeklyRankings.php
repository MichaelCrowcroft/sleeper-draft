<?php

namespace App\Actions\Players;

use App\Models\Player;

class ComputeWeeklyRankings
{
    /**
     * Build a lookup of player_id => weekly projection rank for a given season/week.
     */
    public function handle(int $season, int $week): array
    {
        $weeklyByPosition = Player::calculateWeeklyPositionRankings($season, $week);
        $lookup = [];
        foreach ($weeklyByPosition as $position => $rankedPlayers) {
            foreach ($rankedPlayers as $row) {
                $lookup[$row['player_id']] = $row['rank'];
            }
        }

        return $lookup;
    }
}
