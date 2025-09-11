<?php

namespace App\Console\Commands;

use App\Actions\Players\ComputeSeasonRankings;
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

        // Build position rankings map for the selected season
        $positionRankings = app(ComputeSeasonRankings::class)->handle($season);

        $players = Player::query()
            ->active()
            ->playablePositions()
            ->with(['stats' => function ($q) use ($season) {
                $q->where('season', $season);
            }])
            ->get();

        if ($players->isEmpty()) {
            $this->warn('No active players found.');

            return self::SUCCESS;
        }

        $count = 0;
        DB::transaction(function () use ($players, $season, $positionRankings, &$count) {
            foreach ($players as $player) {
                // Compute summaries dynamically from stats for the requested season
                $totals = $player->calculateSeasonStatTotals($season);

                $pointsPerWeek = [];
                $gamesActive = 0;
                foreach ($player->getStatsForSeason($season)->get() as $weekly) {
                    $stats = is_array($weekly->stats ?? null) ? $weekly->stats : [];
                    $ppr = isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr']) ? (float) $stats['pts_ppr'] : null;
                    $gmsActive = isset($stats['gms_active']) && is_numeric($stats['gms_active']) ? (int) $stats['gms_active'] : null;
                    $isActive = ($gmsActive !== null ? $gmsActive >= 1 : ($ppr !== null));
                    if ($isActive && $ppr !== null) {
                        $pointsPerWeek[] = $ppr;
                        $gamesActive += ($gmsActive !== null ? max(0, (int) $gmsActive) : 1);
                    }
                }

                if (empty($pointsPerWeek)) {
                    $summary = [
                        'total_points' => 'rookie',
                        'min_points' => 0.0,
                        'max_points' => 0.0,
                        'average_points_per_game' => 0.0,
                        'stddev_below' => 0.0,
                        'stddev_above' => 0.0,
                        'games_active' => 0,
                        'snap_percentage_avg' => null,
                        'volatility' => null,
                    ];
                } else {
                    $total = array_sum($pointsPerWeek);
                    $min = min($pointsPerWeek);
                    $max = max($pointsPerWeek);
                    $games = $gamesActive > 0 ? $gamesActive : count($pointsPerWeek);
                    $avg = $games > 0 ? $total / $games : 0.0;
                    $n = count($pointsPerWeek);
                    $variance = 0.0;
                    foreach ($pointsPerWeek as $p) {
                        $variance += ($p - $avg) * ($p - $avg);
                    }
                    $variance = $n > 0 ? $variance / $n : 0.0;
                    $stddev = sqrt($variance);
                    $summary = [
                        'total_points' => $total,
                        'min_points' => $min,
                        'max_points' => $max,
                        'average_points_per_game' => $avg,
                        'stddev_below' => $avg - $stddev,
                        'stddev_above' => $avg + $stddev,
                        'games_active' => $games,
                        'snap_percentage_avg' => null,
                        'volatility' => null,
                    ];
                }

                // Target share average for this season
                $weeklyShares = $player->getWeeklyTargetSharesForSeason($season);
                $targetShareAvg = ! empty($weeklyShares) ? (array_sum($weeklyShares) / count($weeklyShares)) : null;

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

        return self::SUCCESS;
    }
}
