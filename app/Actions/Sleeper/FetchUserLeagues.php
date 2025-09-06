<?php

namespace App\Actions\Sleeper;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class FetchUserLeagues
{
    public function __construct(public ?int $ttlSeconds = 600) {}

    public function execute(string $userId, string $sport = 'nfl', ?int $season = null): array
    {
        $cacheSeason = $season ?? 0; // 0 represents "current" season
        $cacheKey = "sleeper:user:{$userId}:leagues:{$sport}:{$cacheSeason}";

        return Cache::remember($cacheKey, now()->addSeconds($this->ttlSeconds ?? 600), function () use ($userId, $sport, $season) {
            try {
                // Use the fluent API to resolve season automatically when null
                $leagues = Sleeper::user($userId)->leaguesArray($sport, $season !== null ? (string) $season : null);
                return is_array($leagues) ? $leagues : [];
            } catch (\Throwable $e) {
                Log::error('Sleeper user leagues fetch exception: '.$e->getMessage(), ['user_id' => $userId]);
            }
            return [];
        });
    }
}
