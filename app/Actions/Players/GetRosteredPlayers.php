<?php

namespace App\Actions\Players;

use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class GetRosteredPlayers
{
    public function execute(?int $league_id): array
    {
        if(!$league_id) {
            return [];
        }

        $rosters = Sleeper::leagues()->rosters($league_id)->json();
        $owners = Sleeper::leagues()->users($league_id)->json();

        $owners = collect($owners)->keyBy('user_id')->map(function ($owner) {
            return $owner['display_name'] ?? $owner['username'] ?? 'Unknown Owner';
        })->all();

        $players = [];

        foreach($rosters as $roster) {
            $roster_id = $roster['roster_id'] ?? null;
            $owner_id = $roster['owner_id'] ?? null;
            $owner_name = $owner_id ? ($owners[$owner_id] ?? 'Unknown Owner') : 'Unknown Owner';

            $rostered_players = is_array($roster['players'] ?? null) ? $roster['players'] : [];

            foreach ($rostered_players as $rostered_player) {
                if ($rostered_player === null) {
                    continue;
                }

                $players[$rostered_player] = [
                    'owner' => $owner_name,
                    'roster_id' => $roster_id,
                    'owner_id' => $owner_id,
                ];
            }
        }

        return $players;
    }
}
