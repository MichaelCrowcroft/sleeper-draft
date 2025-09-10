<?php

namespace App\Actions\Sleeper;

use Illuminate\Support\Facades\Cache;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class GetUserLeagues
{
    public function execute(string $userId, string $sport = 'nfl', ?int $season = 2025): array
    {
        $cacheKey = "sleeper:user:{$userId}:leagues:{$sport}:{$season}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($userId, $sport, $season) {
            $leagues = Sleeper::user($userId)->leagues($sport, $season);
            return $leagues->json();
        });
    }
}
