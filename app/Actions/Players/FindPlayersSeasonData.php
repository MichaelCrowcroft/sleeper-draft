<?php

namespace App\Actions\Players;

use App\Models\Player;
use Illuminate\Support\Collection;

class FindPlayersSeasonData
{
    /**
     * Find players by player_id (exact) or by name (partial, case-insensitive).
     * Eager-load season stats/projection summaries.
     *
     * @return Collection<int, \App\Models\Player>
     */
    public function execute(?string $playerId, ?string $name): Collection
    {
        $query = Player::query()->with(['stats2024', 'projections2025']);

        if ($playerId) {
            $query->where('player_id', $playerId);
        } elseif ($name) {
            $needle = trim($name);
            $query->where(function ($q) use ($needle) {
                $q->where('full_name', 'like', '%'.$needle.'%')
                    ->orWhere('search_full_name', 'like', '%'.strtolower($needle).'%')
                    ->orWhere('first_name', 'like', '%'.$needle.'%')
                    ->orWhere('last_name', 'like', '%'.$needle.'%');
            })->orderBy('search_rank');
        }

        return $query->get();
    }
}
