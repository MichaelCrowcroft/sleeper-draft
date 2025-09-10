<?php

namespace App\Actions\Players;

use App\Models\Player;
use Illuminate\Support\Facades\Cache;

class FetchAvailablePositions
{
    public function execute(): array
    {
        return Cache::remember('player_positions', now()->addHours(1), function () {
            return Player::whereNotNull('position')
                ->where('active', true)
                ->whereIn('position', ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'])
                ->distinct()
                ->pluck('position')
                ->sort()
                ->values()
                ->toArray();
        });
    }
}
