<?php

namespace App\Actions\Players;

use App\Models\Player;
use Illuminate\Support\Collection;

class ListPlayersByAdp
{
    /**
     * Return players ordered by ADP (ascending). Optionally filter by position.
     *
     * @param  string|null  $position  Position code (e.g., QB, RB, WR, TE)
     * @return Collection<int, \App\Models\Player>
     */
    public function execute(?string $position = null): Collection
    {
        return Player::query()
            ->whereNotNull('adp')
            ->position($position)
            ->orderByAdpNullLast('asc')
            ->get();
    }
}
