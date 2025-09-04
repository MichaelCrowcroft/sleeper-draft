<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class UpdatePlayerProjectionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes timeout

    public $tries = 3; // Retry up to 3 times

    public $backoff = [30, 60, 120]; // Exponential backoff

    public function __construct(
        public string $playerId,
        public string $season = '2024',
        public string $seasonType = 'regular',
        public ?int $delaySeconds = null
    ) {}

    public function handle(): void
    {
        if ($this->delaySeconds) {
            sleep($this->delaySeconds);
        }

        Artisan::call('sleeper:player:projections', [
            'player-id' => $this->playerId,
            '--season' => $this->season,
            '--season-type' => $this->seasonType,
        ]);

    }
}
