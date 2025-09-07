<?php

namespace App\Actions\Sleeper;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class FetchLeague
{
    public function __construct(public ?int $ttlSeconds = 600) {}

    public function execute(string $leagueId): array
    {
        $cacheKey = 'sleeper:league:'.$leagueId;

        return Cache::remember($cacheKey, now()->addSeconds($this->ttlSeconds ?? 600), function () use ($leagueId) {
            try {
                $resp = Sleeper::leagues()->get($leagueId);
                if ($resp->successful()) {
                    $data = $resp->json();

                    return is_array($data) ? $data : [];
                }

                Log::warning('Sleeper league fetch failed', ['status' => $resp->status(), 'league_id' => $leagueId]);
            } catch (\Throwable $e) {
                Log::error('Sleeper league fetch exception: '.$e->getMessage(), ['league_id' => $leagueId]);
            }

            return [];
        });
    }

    public function clearCache(string $leagueId): void
    {
        $cacheKey = 'sleeper:league:'.$leagueId;
        Cache::forget($cacheKey);
    }
}
