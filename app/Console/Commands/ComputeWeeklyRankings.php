<?php

namespace App\Console\Commands;

use App\Models\PlayerStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ComputeWeeklyRankings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rankings:compute-weekly {--season=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute and persist weekly position rankings onto player_stats.weekly_ranking';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $season = (int) $this->option('season');

        // Process both regular and post season types
        $seasonTypes = ['regular', 'post'];
        $processedAny = false;
        foreach ($seasonTypes as $seasonType) {
            // Gather all distinct weeks to process for the season & season type
            $weeks = PlayerStats::query()
                ->where('season', $season)
                ->where('season_type', $seasonType)
                ->distinct()
                ->orderBy('week')
                ->pluck('week')
                ->map(fn ($w) => (int) $w)
                ->all();

            if (empty($weeks)) {
                continue;
            }

            $processedAny = true;
            // Iterate weeks and compute position ranks based on actual pts_ppr
            foreach ($weeks as $week) {
                // Fetch stats joined with players to know positions
                $rows = PlayerStats::query()
                    ->where('season', $season)
                    ->where('week', $week)
                    ->where('season_type', $seasonType)
                    ->join('players', 'players.player_id', '=', 'player_stats.player_id')
                    ->get(['player_stats.player_id', 'player_stats.stats', 'players.position']);

                if ($rows->isEmpty()) {
                    continue;
                }

                // Group by position and rank by pts_ppr desc
                $byPosition = $rows->groupBy('position');

                DB::transaction(function () use ($byPosition, $season, $week, $seasonType) {
                    foreach ($byPosition as $position => $items) {
                        $scored = [];
                        foreach ($items as $row) {
                            $stats = is_array($row->stats ?? null) ? $row->stats : [];
                            $pts = isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr']) ? (float) $stats['pts_ppr'] : 0.0;
                            $scored[] = ['player_id' => $row->player_id, 'pts' => $pts];
                        }

                        usort($scored, fn ($a, $b) => $b['pts'] <=> $a['pts']);

                        $rank = 1;
                        foreach ($scored as $row) {
                            PlayerStats::query()
                                ->where('player_id', $row['player_id'])
                                ->where('season', $season)
                                ->where('week', $week)
                                ->where('season_type', $seasonType)
                                ->update(['weekly_ranking' => $rank]);
                            $rank++;
                        }
                    }
                });

                $this->line("Updated rankings for season {$season}, week {$week} ({$seasonType}).");
            }
        }

        if (! $processedAny) {
            $this->warn('No stats found for the given season.');
        }

        $this->info('Weekly rankings update completed!');

        return self::SUCCESS;
    }
}
