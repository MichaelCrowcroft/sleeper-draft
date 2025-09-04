<?php

namespace App\Console\Commands;

use App\Jobs\UpdatePlayerStatsJob;
use App\Models\Player;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
                           {--limit= : Limit the number of players to process (for testing)}
                           {--queue=default : Queue name to dispatch jobs to}
                           {--force : Skip confirmation prompt}';

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
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $queue = $this->option('queue');
        $force = $this->option('force');

        // Calculate rate per minute
        $ratePerMinute = (60 / ($delayMs / 1000)) * $batchSize;

        if ($ratePerMinute > 250) {
            $this->error("⚠️  Rate limit exceeded! Current configuration would process {$ratePerMinute} players/minute (max: 250)");
            $this->error("Adjust --batch-size or --delay to stay within limits.");
            return;
        }

        // Get all players
        $query = Player::query()->whereNotNull('player_id');

        if ($limit) {
            $query->limit($limit);
        }

        $players = $query->get();

        if ($players->isEmpty()) {
            $this->warn('No players found in database.');
            return;
        }

        $totalPlayers = $players->count();

        // Confirmation
        if (!$force) {
            $this->info("📊 About to dispatch stats update jobs:");
            $this->info("   • Season: {$season}");
            $this->info("   • Season Type: {$seasonType}");
            $this->info("   • Total Players: {$totalPlayers}");
            $this->info("   • Batch Size: {$batchSize} players");
            $this->info("   • Delay: {$delayMs}ms between batches");
            $this->info("   • Rate: ~" . round($ratePerMinute) . " players/minute");
            $this->info("   • Queue: {$queue}");
            $this->info("   • Estimated Time: ~" . round(($totalPlayers / $ratePerMinute)) . " minutes");

            if (!$this->confirm('Do you want to continue?')) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        $this->info("🚀 Starting bulk stats update for {$totalPlayers} players...");

        $progressBar = $this->output->createProgressBar($totalPlayers);
        $progressBar->start();

        $batches = $players->chunk($batchSize);
        $processed = 0;

        foreach ($batches as $batch) {
            // Dispatch jobs for this batch
            foreach ($batch as $player) {
                UpdatePlayerStatsJob::dispatch(
                    $player->player_id,
                    $season,
                    $seasonType
                )->onQueue($queue);

                $processed++;
                $progressBar->advance();
            }

            // Rate limiting delay between batches (except for the last batch)
            if ($processed < $totalPlayers) {
                $this->info("\n⏱️  Waiting " . ($delayMs / 1000) . "s before next batch...");
                usleep($delayMs * 1000); // Convert ms to microseconds
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        Log::info("Dispatched {$totalPlayers} player stats update jobs", [
            'season' => $season,
            'season_type' => $seasonType,
            'batch_size' => $batchSize,
            'queue' => $queue,
        ]);

        $this->info("✅ Successfully dispatched {$totalPlayers} player stats update jobs!");
        $this->info("📝 Check queue status with: php artisan queue:work --queue={$queue}");
        $this->info("📊 Monitor progress with: php artisan queue:status");
    }
}
