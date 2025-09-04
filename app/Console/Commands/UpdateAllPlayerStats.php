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
                           {--batch-size=50 : Number of players to process per batch (max 250)}
                           {--delay=12000 : Delay between batches in milliseconds (default: 12000ms = 12s for 50 players/min)}
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
        $batchSize = min((int) $this->option('batch-size'), 250); // Max 250 per batch
        $delayMs = (int) $this->option('delay');

        // Get all players
        $query = Player::query()->whereNotNull('player_id');

        $players = $query->get();

        $totalPlayers = $players->count();

        $batches = $players->chunk($batchSize);
        $processed = 0;

        foreach ($batches as $batch) {
            foreach ($batch as $player) {
                UpdatePlayerStatsJob::dispatch(
                    $player->player_id,
                    $season,
                    $seasonType
                )->onQueue('default');
            }

            // Rate limiting delay between batches (except for the last batch)
            if ($processed < $totalPlayers) {
                usleep($delayMs * 1000); // Convert ms to microseconds
            }
        }
    }
}
