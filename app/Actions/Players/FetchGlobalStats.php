<?php

namespace App\Actions\Players;

use App\Models\Player;
use Illuminate\Support\Facades\Cache;

class FetchGlobalStats
{
    public function execute(): array
    {
        return Cache::remember('players:global_stats', now()->addMinutes(30), function () {
            $totalPlayers = Player::where('active', true)->count();
            $injuredPlayers = Player::where('active', true)
                ->whereNotNull('injury_status')
                ->where('injury_status', '!=', 'Healthy')
                ->count();

            $positions = Player::where('active', true)
                ->whereIn('position', ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'])
                ->selectRaw('position, COUNT(*) as count')
                ->groupBy('position')
                ->pluck('count', 'position')
                ->toArray();

            $teams = Player::where('active', true)
                ->whereNotNull('team')
                ->distinct()
                ->pluck('team')
                ->toArray();

            return [
                'total_players' => $totalPlayers,
                'injured_players' => $injuredPlayers,
                'players_by_position' => $positions,
                'players_by_team' => $teams,
            ];
        });
    }
}
