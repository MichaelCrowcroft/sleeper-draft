<?php

namespace App\Actions\Players;

use App\Models\Player;
use Illuminate\Support\Collection;

class ListTrendingPlayers
{
    /**
     * Return players ordered by trending column (adds_24h or drops_24h) desc.
     * Optionally filter by position.
     */
    public function execute(string $type, ?string $position = null): Collection
    {
        $column = $type === 'drop' ? 'drops_24h' : 'adds_24h';

        $query = Player::whereNotNull($column)->orderBy($column, 'desc');
        if (is_string($position) && $position !== '') {
            $query->where('position', strtoupper($position));
        }

        return $query->get();
    }
}
