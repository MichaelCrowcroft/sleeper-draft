<?php

namespace App\Actions\Players;

use App\Models\Player;
use Illuminate\Support\Facades\Cache;

class FetchAvailableTeams
{
    public function execute(): array
    {
        return Cache::remember('player_teams', now()->addHours(1), function () {
            return Player::whereNotNull('team')
                ->where('active', true)
                ->distinct()
                ->pluck('team')
                ->sort()
                ->values()
                ->toArray();
        });
    }
}
