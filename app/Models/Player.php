<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'fantasy_positions' => 'array',
        'active' => 'boolean',
        'number' => 'integer',
        'age' => 'integer',
        'years_exp' => 'integer',
        'depth_chart_order' => 'integer',
        'weight' => 'integer',
        'news_updated' => 'integer',
        'birth_date' => 'date',
        'injury_start_date' => 'date',
        'raw' => 'array',
        'adds_24h' => 'integer',
        'drops_24h' => 'integer',
        'times_drafted' => 'integer',
        'adp_high' => 'float',
        'adp_low' => 'float',
        'adp_stdev' => 'float',
        'bye_week' => 'integer',
    ];

    /**
     * Get the player's stats for each week.
     */
    public function stats(): HasMany
    {
        return $this->hasMany(PlayerStats::class, 'player_id', 'player_id');
    }

    /**
     * Get the player's projections for each week.
     */
    public function projections(): HasMany
    {
        return $this->hasMany(PlayerProjections::class, 'player_id', 'player_id');
    }

    /**
     * Get stats for a specific season.
     */
    public function getStatsForSeason(int $season): HasMany
    {
        return $this->stats()->where('season', $season)->orderBy('week');
    }

    /**
     * Get projections for a specific season.
     */
    public function getProjectionsForSeason(int $season): HasMany
    {
        return $this->projections()->where('season', $season)->orderBy('week');
    }

    /**
     * Get stats for a specific season and week.
     */
    public function getStatsForWeek(int $season, int $week)
    {
        return $this->stats()
            ->where('season', $season)
            ->where('week', $week)
            ->first();
    }

    /**
     * Get projections for a specific season and week.
     */
    public function getProjectionsForWeek(int $season, int $week)
    {
        return $this->projections()
            ->where('season', $season)
            ->where('week', $week)
            ->first();
    }

    /**
     * Relationship for projections limited to the 2025 season.
     */
    public function projections2025(): HasMany
    {
        return $this->projections()->where('season', 2025);
    }

    /**
     * Compute PPR projection summary for 2025 season: totals/min/max/avg/stddev.
     */
    public function getSeason2025ProjectionSummary(): array
    {
        if ($this->relationLoaded('projections2025')) {
            $collection = $this->getRelation('projections2025');
        } else {
            $collection = $this->projections2025()->get();
        }

        $points = [];
        $totalProjectedGames = 0;

        foreach ($collection as $weeklyProjection) {
            // Support both JSON stats schema and flattened columns
            $stats = is_array($weeklyProjection->stats ?? null) ? $weeklyProjection->stats : null;
            $ppr = null;
            $gms = null;

            if ($stats) {
                $ppr = isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr']) ? (float) $stats['pts_ppr'] : null;
                $gms = isset($stats['gms_active']) && is_numeric($stats['gms_active']) ? (int) $stats['gms_active'] : null;
            }

            if ($ppr === null && isset($weeklyProjection->pts_ppr) && is_numeric($weeklyProjection->pts_ppr)) {
                $ppr = (float) $weeklyProjection->pts_ppr;
            }

            if ($gms === null && isset($weeklyProjection->gms_active) && is_numeric($weeklyProjection->gms_active)) {
                $gms = (int) $weeklyProjection->gms_active;
            }

            if ($ppr !== null) {
                $points[] = $ppr;
                $totalProjectedGames += max(0, (int) ($gms ?? 1));
            }
        }

        if (empty($points)) {
            return [
                'total_points' => 0.0,
                'min_points' => 0.0,
                'max_points' => 0.0,
                'average_points_per_game' => 0.0,
                'stddev_below' => 0.0,
                'stddev_above' => 0.0,
                'games' => 0,
            ];
        }

        $total = array_sum($points);
        $min = min($points);
        $max = max($points);
        $games = $totalProjectedGames > 0 ? $totalProjectedGames : count($points);
        $avg = $games > 0 ? $total / $games : 0.0;

        $n = count($points);
        $variance = 0.0;
        foreach ($points as $p) {
            $variance += ($p - $avg) * ($p - $avg);
        }
        $variance = $n > 0 ? $variance / $n : 0.0;
        $stddev = sqrt($variance);

        return [
            'total_points' => $total,
            'min_points' => $min,
            'max_points' => $max,
            'average_points_per_game' => $avg,
            'stddev_below' => $avg - $stddev,
            'stddev_above' => $avg + $stddev,
            'games' => $games,
        ];
    }

    /**
     * Get projected PPR points for a specific season/week if available.
     */
    public function getProjectedPointsForWeek(int $season, int $week): ?float
    {
        $weekly = $this->getProjectionsForWeek($season, $week);

        if (! $weekly) {
            return null;
        }

        // Prefer nested stats array if present, otherwise fallback to flattened column
        $stats = $weekly->stats ?? null;
        if (is_array($stats) && isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr'])) {
            return (float) $stats['pts_ppr'];
        }

        if (isset($weekly->pts_ppr) && is_numeric($weekly->pts_ppr)) {
            return (float) $weekly->pts_ppr;
        }

        return null;
    }

    /**
     * Compute per-game projection averages for all numeric stats in a season.
     * If gms_active is provided per week, it is used for per-game normalization,
     * otherwise each week counts as one game.
     *
     * @return array<string, float>
     */
    public function getSeason2025ProjectionsAverages(): array
    {
        if ($this->relationLoaded('projections2025')) {
            $collection = $this->getRelation('projections2025');
        } else {
            $collection = $this->projections2025()->get();
        }

        $sumByMetric = [];
        $gamesCount = 0;

        foreach ($collection as $weeklyProjection) {
            $stats = is_array($weeklyProjection->stats ?? null) ? $weeklyProjection->stats : [];

            $gms = null;
            if (isset($stats['gms_active']) && is_numeric($stats['gms_active'])) {
                $gms = (int) $stats['gms_active'];
            } elseif (isset($weeklyProjection->gms_active) && is_numeric($weeklyProjection->gms_active)) {
                $gms = (int) $weeklyProjection->gms_active;
            }

            $gamesCount += ($gms !== null ? max(0, $gms) : 1);

            foreach ($stats as $metric => $value) {
                if (is_numeric($value)) {
                    $sumByMetric[$metric] = ($sumByMetric[$metric] ?? 0.0) + (float) $value;
                }
            }
        }

        if ($gamesCount <= 0) {
            return [];
        }

        $avgByMetric = [];
        foreach ($sumByMetric as $metric => $sum) {
            $avgByMetric[$metric] = $sum / $gamesCount;
        }

        // Also include flattened fields if present on projections (e.g., pts_ppr)
        // Compute their average similarly.
        $flattenedSum = [];
        $flattenedGames = 0;
        foreach ($collection as $weeklyProjection) {
            $gms = isset($weeklyProjection->gms_active) && is_numeric($weeklyProjection->gms_active)
                ? max(0, (int) $weeklyProjection->gms_active)
                : 1;
            $flattenedGames += $gms;

            foreach (['pts_ppr'] as $flatField) {
                if (isset($weeklyProjection->{$flatField}) && is_numeric($weeklyProjection->{$flatField})) {
                    $flattenedSum[$flatField] = ($flattenedSum[$flatField] ?? 0.0) + ((float) $weeklyProjection->{$flatField});
                }
            }
        }

        if ($flattenedGames > 0) {
            foreach ($flattenedSum as $metric => $sum) {
                // Prefer nested stats if already computed, otherwise use flattened
                if (! array_key_exists($metric, $avgByMetric)) {
                    $avgByMetric[$metric] = $sum / $flattenedGames;
                }
            }
        }

        return $avgByMetric;
    }

    /**
     * Calculate aggregated season totals by summing all numeric values in the weekly 'stats' arrays.
     */
    public function calculateSeasonStatTotals(int $season): array
    {
        $totals = [];

        $this->getStatsForSeason($season)->get()->each(function (PlayerStats $weeklyStats) use (&$totals) {
            $stats = $weeklyStats->stats ?? [];

            foreach ($stats as $metric => $value) {
                if (is_numeric($value)) {
                    $totals[$metric] = ($totals[$metric] ?? 0) + (float) $value;
                }
            }
        });

        return $totals;
    }

    /**
     * Relationship for stats limited to the 2024 season.
     */
    public function stats2024(): HasMany
    {
        return $this->stats()->where('season', 2024);
    }

    /**
     * Compute weekly position ranks for a given season based on actual PPR points.
     * Returns an associative array keyed by week (int) with rank (int) values.
     */
    public function getWeeklyPositionRanksForSeason(int $season): array
    {
        $position = $this->position ?? null;
        if (! $position) {
            return [];
        }

        // Fetch all weekly stats for the season for players at this position
        $weeklyStats = PlayerStats::query()
            ->where('season', $season)
            ->whereHas('player', function ($q) use ($position) {
                $q->where('position', $position);
            })
            ->get(['player_id', 'season', 'week', 'stats']);

        if ($weeklyStats->isEmpty()) {
            return [];
        }

        // Group by week, then rank by pts_ppr descending
        $byWeek = $weeklyStats->groupBy('week');
        $ranksByWeek = [];

        foreach ($byWeek as $week => $items) {
            $scored = [];
            foreach ($items as $ws) {
                $stats = is_array($ws->stats ?? null) ? $ws->stats : [];
                if (isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr'])) {
                    $scored[] = [
                        'player_id' => $ws->player_id,
                        'pts' => (float) $stats['pts_ppr'],
                    ];
                }
            }

            if (empty($scored)) {
                continue;
            }

            usort($scored, fn ($a, $b) => $b['pts'] <=> $a['pts']);

            $rank = 1;
            foreach ($scored as $row) {
                if ($row['player_id'] === $this->player_id) {
                    $ranksByWeek[(int) $week] = $rank;
                    break;
                }
                $rank++;
            }
        }

        return $ranksByWeek;
    }

    /**
     * Calculate position-based rankings for 2024 season based on total PPR points.
     *
     * @return array Array with player rankings by position
     */
    public static function calculatePositionRankings2024(): array
    {
        $players = self::where('active', true)
            ->whereIn('position', ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'])
            ->with('stats2024')
            ->get();

        $positionRankings = [];
        $playersWithPoints = [];

        // First, calculate total points for each player
        foreach ($players as $player) {
            $summary = $player->getSeason2024Summary();

            if ($summary['games_active'] > 0) {
                $playersWithPoints[] = [
                    'player' => $player,
                    'total_points' => $summary['total_points'],
                    'position' => $player->position,
                    'player_id' => $player->player_id,
                ];
            }
        }

        // Group by position and sort by total points descending
        $byPosition = collect($playersWithPoints)->groupBy('position');

        foreach ($byPosition as $position => $positionPlayers) {
            $sortedPlayers = $positionPlayers->sortByDesc('total_points')->values();

            $positionRankings[$position] = [];
            $rank = 1;

            foreach ($sortedPlayers as $playerData) {
                $positionRankings[$position][] = [
                    'player_id' => $playerData['player_id'],
                    'rank' => $rank,
                    'total_points' => $playerData['total_points'],
                ];
                $rank++;
            }
        }

        return $positionRankings;
    }

    /**
     * Calculate position-based rankings for weekly projections based on current week PPR points.
     *
     * @param  int  $season  The season year (e.g., 2025)
     * @param  int  $week  The week number
     * @return array Array with player rankings by position for the specified week
     */
    public static function calculateWeeklyPositionRankings(int $season, int $week): array
    {
        $players = self::where('active', true)
            ->whereIn('position', ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'])
            ->with(['projections' => function ($query) use ($season, $week) {
                $query->where('season', $season)->where('week', $week);
            }])
            ->get();

        $positionRankings = [];
        $playersWithPoints = [];

        // First, calculate projected points for each player for this week
        foreach ($players as $player) {
            $weeklyProjection = $player->projections->first();

            if ($weeklyProjection) {
                $stats = is_array($weeklyProjection->stats ?? null) ? $weeklyProjection->stats : null;
                $pts = null;

                if ($stats && isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr'])) {
                    $pts = (float) $stats['pts_ppr'];
                } elseif (isset($weeklyProjection->pts_ppr) && is_numeric($weeklyProjection->pts_ppr)) {
                    $pts = (float) $weeklyProjection->pts_ppr;
                }

                if ($pts !== null && $pts > 0) {
                    $playersWithPoints[] = [
                        'player' => $player,
                        'weekly_points' => $pts,
                        'position' => $player->position,
                        'player_id' => $player->player_id,
                    ];
                }
            }
        }

        // Group by position and sort by weekly points descending
        $byPosition = collect($playersWithPoints)->groupBy('position');

        foreach ($byPosition as $position => $positionPlayers) {
            $sortedPlayers = $positionPlayers->sortByDesc('weekly_points')->values();

            $positionRankings[$position] = [];
            $rank = 1;

            foreach ($sortedPlayers as $playerData) {
                $positionRankings[$position][] = [
                    'player_id' => $playerData['player_id'],
                    'rank' => $rank,
                    'weekly_points' => $playerData['weekly_points'],
                ];
                $rank++;
            }
        }

        return $positionRankings;
    }

    /**
     * Accessor-like helper that returns aggregated season stats for 2024 if the relation is (pre)loaded.
     * If not loaded, it will load from DB efficiently and compute totals.
     */
    public function getSeason2024Totals(): array
    {
        if ($this->relationLoaded('stats2024')) {
            $collection = $this->getRelation('stats2024');
        } else {
            $collection = $this->stats2024()->get();
        }

        $totals = [];
        foreach ($collection as $weeklyStats) {
            $stats = $weeklyStats->stats ?? [];
            foreach ($stats as $metric => $value) {
                if (is_numeric($value)) {
                    $totals[$metric] = ($totals[$metric] ?? 0) + (float) $value;
                }
            }
        }

        return $totals;
    }

    /**
     * Compute PPR summary for 2024 season: totals/min/max/avg/stddev.
     */
    public function getSeason2024Summary(): array
    {
        if ($this->relationLoaded('stats2024')) {
            $collection = $this->getRelation('stats2024');
        } else {
            $collection = $this->stats2024()->get();
        }

        $points = [];
        $totalGamesActive = 0;
        $snapData = [];

        foreach ($collection as $weeklyStats) {
            $stats = $weeklyStats->stats ?? [];
            $ppr = isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr']) ? (float) $stats['pts_ppr'] : null;
            $gmsActive = isset($stats['gms_active']) && is_numeric($stats['gms_active']) ? (int) $stats['gms_active'] : null;

            // Consider a game active if gms_active >= 1, otherwise if pts exist assume 1
            $isActive = ($gmsActive !== null ? $gmsActive >= 1 : ($ppr !== null));

            if ($isActive && $ppr !== null) {
                $points[] = $ppr;
                $totalGamesActive += ($gmsActive !== null ? max(0, (int) $gmsActive) : 1);
            }

            // Collect snap percentage data if available
            if (isset($stats['snap_pct']) && is_numeric($stats['snap_pct'])) {
                $snapData[] = (float) $stats['snap_pct'];
            }
        }

        if (empty($points)) {
            return [
                'total_points' => 'rookie',
                'min_points' => 0.0,
                'max_points' => 0.0,
                'average_points_per_game' => 0.0,
                'stddev_below' => 0.0,
                'stddev_above' => 0.0,
                'games_active' => 0,
                'snap_percentage_avg' => null,
                'position_rank' => null,
            ];
        }

        $total = array_sum($points);
        $min = min($points);
        $max = max($points);
        $games = $totalGamesActive > 0 ? $totalGamesActive : count($points);
        $avg = $games > 0 ? $total / $games : 0.0;

        $n = count($points);
        $variance = 0.0;
        foreach ($points as $p) {
            $variance += ($p - $avg) * ($p - $avg);
        }
        $variance = $n > 0 ? $variance / $n : 0.0; // population variance
        $stddev = sqrt($variance);

        // Calculate average snap percentage
        $snapPercentageAvg = null;
        if (! empty($snapData)) {
            $snapPercentageAvg = array_sum($snapData) / count($snapData);
        }

        return [
            'total_points' => $total,
            'min_points' => $min,
            'max_points' => $max,
            'average_points_per_game' => $avg,
            'stddev_below' => $avg - $stddev,
            'stddev_above' => $avg + $stddev,
            'games_active' => $games,
            'snap_percentage_avg' => $snapPercentageAvg,
            'position_rank' => null, // Will be set by calling method
        ];
    }
}
