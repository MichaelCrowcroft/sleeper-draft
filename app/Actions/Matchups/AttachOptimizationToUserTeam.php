<?php

namespace App\Actions\Matchups;

use Illuminate\Support\Facades\Auth;

class AttachOptimizationToUserTeam
{
    /**
     * Loop through enriched+merged matchups and attach lineup_optimization for the logged in user's team only.
     *
     * @param  array<string,array<int,array<string,mixed>>>  $matchups
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function execute(array $matchups, int $season, int $week): array
    {
        $optimizer = app(OptimizeLineup::class);
        $points_builder = new BuildTeamPointsMap;

        foreach ($matchups as $mid => &$teams) {
            foreach ($teams as &$team) {
                if (! is_array($team) || ! isset($team['owner_id'])) {
                    continue;
                }

                if ((string) ($team['owner_id'] ?? '') !== (string) (Auth::user()->sleeper_user_id ?? '')) {
                    continue;
                }

                // Convert slot-indexed starters (may include nulls) into player_id list
                $current_starters = array_values(array_filter(array_map(function ($p) {
                    return is_array($p) ? ($p['player_id'] ?? null) : $p;
                }, (array) ($team['starters'] ?? []))));

                $bench_players = array_values(array_filter(array_map(function ($p) {
                    return is_array($p) ? ($p['player_id'] ?? null) : $p;
                }, (array) ($team['players'] ?? []))));

                $points = $points_builder->execute($team);

                $team['lineup_optimization'] = $optimizer->execute(
                    $current_starters,
                    $bench_players,
                    $points,
                    $season,
                    $week,
                    $team['roster_slots'] ?? null
                );
            }
        }

        return $matchups;
    }
}
