<?php

namespace App\Actions\Players;

use App\Models\Player;

class ComputeRankings
{
    /**
     * Build a lookup of player_id => 2024 season position rank.
     * Uses Player::calculatePositionRankings2024() and flattens to a map.
     */
    public function season2024(): array
    {
        $positionRankings = Player::calculatePositionRankings2024();
        $lookup = [];
        foreach ($positionRankings as $position => $rankedPlayers) {
            foreach ($rankedPlayers as $row) {
                $lookup[$row['player_id']] = $row['rank'];
            }
        }

        return $lookup;
    }

    /**
     * Build a lookup of player_id => weekly projection rank for a given season/week.
     */
    public function weekly(int $season, int $week): array
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
