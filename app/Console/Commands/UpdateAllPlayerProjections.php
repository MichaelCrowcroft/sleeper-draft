<?php

namespace App\Console\Commands;

use App\Jobs\UpdatePlayerProjectionsJob;
use App\Models\Player;
use Illuminate\Console\Command;

class UpdateAllPlayerProjections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sleeper:players:projections:update-all
                           {--season=2024 : The season year (default: current year 2024)}
                           {--season-type=regular : Season type (regular, postseason)}
                           {--rate-limit=250 : Maximum jobs per minute (default: 250)}
                           ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch jobs to update projections for all players with rate limiting (max 250/min)';

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
            UpdatePlayerProjectionsJob::dispatch(
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
