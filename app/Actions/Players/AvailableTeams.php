<?php

namespace App\Actions\Players;

use App\Models\Player;
use Illuminate\Support\Facades\Cache;

class AvailableTeams
{
    public function execute(): array
    {
        return Cache::remember('player_teams', now()->addDays(120), function () {
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
