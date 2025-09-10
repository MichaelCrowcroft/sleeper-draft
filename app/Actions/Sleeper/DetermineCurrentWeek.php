<?php

namespace App\Actions\Sleeper;

use Illuminate\Support\Facades\Cache;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class DetermineCurrentWeek
{
    public function execute(string $sport = 'nfl'): array
    {
        return Cache::remember('sleeper:state:current:'.$sport, now()->addhours(6), function () use ($sport) {
            $state = Sleeper::state()->current($sport)->json();

            return [
                'season' => $state['season'],
                'week' => $state['week'],
            ];
        });
    }
}
