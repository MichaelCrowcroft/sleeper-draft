<?php

namespace App\Actions\Sleeper;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class FetchRosters
{
    public function __construct(public ?int $ttlSeconds = 60) {}

    public function execute(string $leagueId): array
    {
        $cacheKey = 'sleeper:rosters:'.$leagueId;

        return Cache::remember($cacheKey, now()->addSeconds($this->ttlSeconds ?? 60), function () use ($leagueId) {
            try {
                $resp = Sleeper::league()->rosters($leagueId);
                if ($resp->successful()) {
                    $data = $resp->json();
                    return is_array($data) ? $data : [];
                }

                Log::warning('Sleeper rosters fetch failed', ['status' => $resp->status(), 'league_id' => $leagueId]);
            } catch (\Throwable $e) {
                Log::error('Sleeper rosters fetch exception: '.$e->getMessage(), ['league_id' => $leagueId]);
            }

            return [];
        });
    }
}
