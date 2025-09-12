<?php

namespace App\Actions\Players;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AddOwnerToPlayers
{
    public function execute(Collection|LengthAwarePaginator $players, ?array $rostered_players = []): Collection|LengthAwarePaginator
    {
        if(!empty($rostered_players)) {
            foreach ($players as $player) {
                $roster_info = $rostered_players[$player->player_id] ?? null;
                $player->owner_or_free_agent = $roster_info ? ($roster_info['owner'] ?? 'Unknown Owner') : null;
            }
        }

        return $players;
    }
}
