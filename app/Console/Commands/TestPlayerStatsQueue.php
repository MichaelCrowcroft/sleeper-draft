<?php

namespace App\Console\Commands;

use App\Jobs\UpdatePlayerStatsJob;
use App\Models\Player;
use Illuminate\Console\Command;

class TestPlayerStatsQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:player-stats-queue
                           {--count=5 : Number of players to test with}
                           {--season=2025 : The season year}
                           {--season-type=regular : Season type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the player stats queue system with a small batch of players';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $count = (int) $this->option('count');
        $season = $this->option('season');
        $seasonType = $this->option('season-type');

        // Get a few players for testing
        $players = Player::query()
            ->whereNotNull('player_id')
            ->where('active', true)
            ->limit($count)
            ->get();

        if ($players->isEmpty()) {
            $this->error('No active players found in database. Make sure you have imported players first.');

            return;
        }

        $this->info("🧪 Testing player stats queue with {$players->count()} players...");

        foreach ($players as $player) {
            $this->info("Dispatching job for player: {$player->first_name} {$player->last_name} (ID: {$player->player_id})");

            UpdatePlayerStatsJob::dispatch(
                $player->player_id,
                $season,
                $seasonType
            )->onQueue('default');
        }

        $this->info("✅ Dispatched {$players->count()} test jobs!");
        $this->info("📝 Run 'php artisan queue:work' in another terminal to process the jobs");
        $this->info("📊 Check queue status with 'php artisan queue:status'");
    }
}
