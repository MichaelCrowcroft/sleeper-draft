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
        $positionRankings = $this->positionRankings2024ByPosition();
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

    /**
     * Build position-based rankings for the 2024 season grouped by position.
     *
     * @return array<string, array<int, array{player_id:string|int, rank:int, total_points:float}>>
     */
    public function positionRankings2024ByPosition(): array
    {
        $players = Player::query()
            ->active()
            ->playablePositions()
            ->with('stats2024')
            ->get();

        $playersWithPoints = [];

        foreach ($players as $player) {
            $summary = $player->getSeason2024Summary();

            if (($summary['games_active'] ?? 0) > 0) {
                $playersWithPoints[] = [
                    'player' => $player,
                    'total_points' => (float) $summary['total_points'],
                    'position' => $player->position,
                    'player_id' => $player->player_id,
                ];
            }
        }

        $byPosition = collect($playersWithPoints)->groupBy('position');

        $positionRankings = [];
        foreach ($byPosition as $position => $positionPlayers) {
            $sortedPlayers = $positionPlayers->sortByDesc('total_points')->values();

            $positionRankings[$position] = [];
            $rank = 1;

            foreach ($sortedPlayers as $playerData) {
                $positionRankings[$position][] = [
                    'player_id' => $playerData['player_id'],
                    'rank' => $rank,
                    'total_points' => $playerData['total_points'],
                ];
                $rank++;
            }
        }

        return $positionRankings;
    }
}
