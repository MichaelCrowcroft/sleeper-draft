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
        try {
            Log::info("Starting stats update for player {$this->playerId}");

            // Execute the existing artisan command
            $exitCode = Artisan::call('sleeper:player:stats', [
                'player-id' => $this->playerId,
                '--season' => $this->season,
                '--season-type' => $this->seasonType,
            ]);

            if ($exitCode === 0) {
                Log::info("Successfully updated stats for player {$this->playerId}");
            } else {
                Log::warning("Command failed for player {$this->playerId} with exit code {$exitCode}");
                $this->fail(new \Exception("Artisan command failed with exit code {$exitCode}"));
            }

        } catch (\Exception $e) {
            Log::error("Failed to update stats for player {$this->playerId}: {$e->getMessage()}");
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("UpdatePlayerStatsJob failed for player {$this->playerId}: {$exception->getMessage()}");
    }
}
