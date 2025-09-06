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
        $season = $season ?? (int) date('Y');
        $cacheKey = "sleeper:user:{$userId}:leagues:{$sport}:{$season}";

        return Cache::remember($cacheKey, now()->addSeconds($this->ttlSeconds ?? 600), function () use ($userId, $sport, $season) {
            try {
                $resp = Sleeper::league()->userLeagues($userId, $sport, (string) $season);
                if ($resp->successful()) {
                    $data = $resp->json();
                    return is_array($data) ? $data : [];
                }
                Log::warning('Sleeper user leagues fetch failed', ['status' => $resp->status(), 'user_id' => $userId]);
            } catch (\Throwable $e) {
                Log::error('Sleeper user leagues fetch exception: '.$e->getMessage(), ['user_id' => $userId]);
            }
            return [];
        });
    }
}
