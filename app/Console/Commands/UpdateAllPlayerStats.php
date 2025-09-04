<?php

namespace App\Console\Commands;

use App\Jobs\UpdatePlayerStatsJob;
use App\Models\Player;
use Illuminate\Console\Command;

class UpdateAllPlayerStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sleeper:players:stats:update-all
                           {--season=2025 : The season year (default: 2025)}
                           {--season-type=regular : Season type (regular, postseason)}
                           {--rate-limit=250 : Maximum jobs per minute (default: 250)}
                           ';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch jobs to update stats for all players with rate limiting (max 250/min)';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $season = $this->option('season');
        $seasonType = $this->option('season-type');
        $rateLimit = (int) $this->option('rate-limit');

        $players = Player::query()->whereNotNull('player_id')->get();

        $delaySeconds = $rateLimit > 0 ? ceil(60 / $rateLimit) : 0;

        $currentDelay = 0;
        foreach ($players as $player) {
            UpdatePlayerStatsJob::dispatch(
                $player->player_id,
                $season,
                $seasonType,
                $currentDelay
            )->onQueue('default');

            // Increment delay for next job to maintain rate limiting
            $currentDelay += $delaySeconds;
        }
    }
}
