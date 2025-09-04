<?php

namespace App\Jobs;

use App\Models\PlayerStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class UpdatePlayerStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes timeout
    public $tries = 3; // Retry up to 3 times
    public $backoff = [30, 60, 120]; // Exponential backoff

    public function __construct(
        public string $playerId,
        public string $season = '2025',
        public string $seasonType = 'regular'
    ) {}

    public function handle(): void
    {
        Artisan::call('sleeper:player:stats', [
            'player-id' => $this->playerId,
            '--season' => $this->season,
            '--season-type' => $this->seasonType,
        ]);
    }
}
