<?php

namespace App\Actions\Sleeper;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class FetchMatchups
{
    public function __construct(public ?int $ttlSeconds = 60) {}

    public function execute(string $leagueId, int $week): array
    {
        $cacheKey = 'sleeper:matchups:'.$leagueId.':week:'.$week;

        return Cache::remember($cacheKey, now()->addSeconds($this->ttlSeconds ?? 60), function () use ($leagueId, $week) {
            try {
                $resp = Sleeper::league()->matchups($leagueId, $week);
                if ($resp->successful()) {
                    $data = $resp->json();
                    return is_array($data) ? $data : [];
                }

                Log::warning('Sleeper matchups fetch failed', ['status' => $resp->status(), 'league_id' => $leagueId, 'week' => $week]);
            } catch (\Throwable $e) {
                Log::error('Sleeper matchups fetch exception: '.$e->getMessage(), ['league_id' => $leagueId, 'week' => $week]);
            }

            return [];
        });
    }
}
