<?php

namespace App\Console\Commands;

use App\Actions\Players\ComputeRankings;
use App\Models\Player;
use App\Models\PlayerSeasonSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ComputeSeasonSummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'players:compute-season-summaries {--season=2024}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute and persist per-player season summaries and target share averages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $season = (int) $this->option('season');

        // Build position rankings map for 2024 only (computed via action)
        $positionRankings = [];
        if ($season === 2024) {
            $positionRankings = app(ComputeRankings::class)->season2024();
        }

        $players = Player::query()
            ->active()
            ->playablePositions()
            ->with(['stats2024'])
            ->get();

        if ($players->isEmpty()) {
            $this->warn('No active players found.');

            return self::SUCCESS;
        }

        $count = 0;
        DB::transaction(function () use ($players, $season, $positionRankings, &$count) {
            foreach ($players as $player) {
                // Compute summaries using existing helpers (will compute from stats when no cache present)
                if ($season === 2024) {
                    $summary = $player->getSeason2024Summary();
                    $targetShareAvg = $player->getSeason2024AverageTargetShare();
                } else {
                    // For other seasons, reuse 2024 methods by temporarily swapping relation query
                    // Not implemented yet; skip non-2024 seasons to avoid incorrect data.
                    continue;
                }

                // Normalize rookie/no-data case
                $totalPoints = is_numeric($summary['total_points']) ? (float) $summary['total_points'] : null;

                PlayerSeasonSummary::updateOrCreate(
                    [
                        'player_id' => $player->player_id,
                        'season' => $season,
                    ],
                    [
                        'total_points' => $totalPoints,
                        'min_points' => (float) $summary['min_points'],
                        'max_points' => (float) $summary['max_points'],
                        'average_points_per_game' => (float) $summary['average_points_per_game'],
                        'stddev_below' => (float) $summary['stddev_below'],
                        'stddev_above' => (float) $summary['stddev_above'],
                        'games_active' => (int) $summary['games_active'],
                        'snap_percentage_avg' => isset($summary['snap_percentage_avg']) ? (float) $summary['snap_percentage_avg'] : null,
                        'position_rank' => $positionRankings[$player->player_id] ?? null,
                        'volatility' => $summary['volatility'] ?? null,
                        'target_share_avg' => $targetShareAvg !== null ? (float) $targetShareAvg : null,
                    ]
                );

                $count++;
            }
        });

        $this->info("Computed and stored summaries for {$count} players (season {$season}).");

        return self::SUCCESS;
    }
}
