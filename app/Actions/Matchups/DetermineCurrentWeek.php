<?php

namespace App\Actions\Matchups;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class DetermineCurrentWeek
{
    public function execute(string $sport = 'nfl'): array
    {
        return Cache::remember('sleeper:state:current:'.$sport, now()->addMinutes(5), function () use ($sport) {
            try {
                $resp = Sleeper::state()->current($sport);
                if ($resp->successful()) {
                    $data = $resp->json();

                    $season = isset($data['season']) && is_numeric($data['season'])
                        ? (int) $data['season']
                        : (int) date('Y');

                    $week = isset($data['week']) && is_numeric($data['week'])
                        ? (int) $data['week']
                        : 1;

                    return [
                        'season' => $season,
                        'week' => $week,
                    ];
                }

                Log::warning('Failed to resolve Sleeper current state', ['status' => $resp->status()]);
            } catch (\Throwable $e) {
                Log::error('Sleeper state current failed: '.$e->getMessage());
            }

            return [
                'season' => (int) date('Y'),
                'week' => 1,
            ];
        });
    }
}
