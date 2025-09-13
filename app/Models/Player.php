<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
     * Scope: only active players.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Scope: search by name fields when term provided.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $search = (string) ($term ?? '');
        if ($search === '') {
            return $query;
        }

        return $query->where(function ($sub) use ($search) {
            $sub->where('first_name', 'like', '%'.$search.'%')
                ->orWhere('last_name', 'like', '%'.$search.'%')
                ->orWhere('full_name', 'like', '%'.$search.'%')
                ->orWhere('search_full_name', 'like', '%'.strtolower($search).'%');
        });
    }

    /**
     * Scope: restrict to standard fantasy playable positions.
     */
    public function scopePlayablePositions(Builder $query): Builder
    {
        return $query->whereIn('position', ['QB', 'RB', 'WR', 'TE', 'K', 'DEF']);
    }

    /**
     * Scope: order by first and last name in one call.
     */
    public function scopeOrderByName(Builder $query, string $direction = 'asc'): Builder
    {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $query->orderBy('first_name', $dir)->orderBy('last_name', $dir);
    }

    /**
     * Scope: order by ADP with NULLs always last.
     */
    public function scopeOrderByAdp(Builder $query, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderByRaw('CASE WHEN adp IS NULL THEN 1 ELSE 0 END')
            ->orderBy('adp', $direction);
    }

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
     * Cached season summaries relation.
     */
    public function seasonSummaries(): HasMany
    {
        return $this->hasMany(PlayerSeasonSummary::class, 'player_id', 'player_id');
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
        $weekly = $this->projections()
            ->where('season', $season)
            ->where('week', $week)
            ->first();

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
     * Memoized cache for team targets by (season, week, team) during a single request.
     *
     * @var array<string, float>
     */
    protected static array $teamTargetsMemo = [];

    /**
     * Get total team targets for a week (sum of rec_tgt across all players for the team/week).
     */
    public static function getTeamTargetsForWeek(int $season, int $week, string $team): float
    {
        $key = $season.'-'.$week.'-'.$team;
        if (array_key_exists($key, self::$teamTargetsMemo)) {
            return self::$teamTargetsMemo[$key];
        }

        $rows = PlayerStats::query()
            ->where('season', $season)
            ->where('week', $week)
            ->where('team', $team)
            ->get(['stats']);

        $sum = 0.0;
        foreach ($rows as $row) {
            $s = is_array($row->stats ?? null) ? $row->stats : [];
            if (isset($s['rec_tgt']) && is_numeric($s['rec_tgt'])) {
                $sum += (float) $s['rec_tgt'];
            }
        }

        self::$teamTargetsMemo[$key] = $sum;

        return $sum;
    }

    /**
     * Compute weekly target shares (percent) for a given season.
     *
     * @return array<int, float> week => percent 0-100
     */
    public function getWeeklyTargetSharesForSeason(int $season): array
    {
        $collection = $this->getStatsForSeason($season)->get();
        if ($collection->isEmpty()) {
            return [];
        }

        $shares = [];
        foreach ($collection as $weeklyStats) {
            $stats = is_array($weeklyStats->stats ?? null) ? $weeklyStats->stats : [];
            if (! isset($stats['rec_tgt']) || ! is_numeric($stats['rec_tgt'])) {
                continue;
            }
            $team = $weeklyStats->team ?? $this->team;
            $week = (int) $weeklyStats->week;
            if (! $team) {
                continue;
            }
            $teamTotal = self::getTeamTargetsForWeek($season, $week, (string) $team);
            if ($teamTotal > 0) {
                $shares[$week] = ((float) $stats['rec_tgt'] / $teamTotal) * 100.0;
            }
        }

        return $shares;
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
     * Calculate comprehensive volatility metrics for 2024 season.
     */
    public function getVolatilityMetrics(): array
    {
        if ($this->relationLoaded('stats2024')) {
            $collection = $this->getRelation('stats2024');
        } else {
            $collection = $this->stats2024()->get();
        }

        $points = [];
        $snapPercentages = [];
        $targets = [];
        $rushAttempts = [];
        $activeWeeks = 0;

        // Position-specific thresholds for consistency analysis
        $thresholds = $this->getPositionThresholds();

        foreach ($collection as $weeklyStats) {
            $stats = $weeklyStats->stats ?? [];
            $ppr = isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr']) ? (float) $stats['pts_ppr'] : null;
            $gmsActive = isset($stats['gms_active']) && is_numeric($stats['gms_active']) ? (int) $stats['gms_active'] : null;

            // Consider a game active if gms_active >= 1, otherwise if pts exist assume 1
            $isActive = ($gmsActive !== null ? $gmsActive >= 1 : ($ppr !== null));

            if ($isActive && $ppr !== null) {
                $points[] = $ppr;
                $activeWeeks++;

                // Collect usage metrics
                if (isset($stats['snap_pct']) && is_numeric($stats['snap_pct'])) {
                    $snapPercentages[] = (float) $stats['snap_pct'];
                } elseif (isset($stats['off_snp']) && isset($stats['tm_off_snp']) &&
                         is_numeric($stats['off_snp']) && is_numeric($stats['tm_off_snp']) && (float) $stats['tm_off_snp'] > 0) {
                    $snapPercentages[] = ((float) $stats['off_snp'] / (float) $stats['tm_off_snp']) * 100.0;
                }

                if (isset($stats['rec_tgt']) && is_numeric($stats['rec_tgt'])) {
                    $targets[] = (float) $stats['rec_tgt'];
                }

                if (isset($stats['rush_att']) && is_numeric($stats['rush_att'])) {
                    $rushAttempts[] = (float) $stats['rush_att'];
                }
            }
        }

        if (empty($points)) {
            return [
                'coefficient_of_variation' => null,
                'median_absolute_deviation' => null,
                'interquartile_range' => null,
                'consistency_rate' => null,
                'boom_rate' => null,
                'bust_rate' => null,
                'downside_volatility' => null,
                'usage_volatility' => null,
                'recency_volatility' => null,
                'steadiness_score' => null,
                'safe_floor' => null,
                'spike_factor' => null,
                'boom_bust_ratio' => null,
            ];
        }

        // Basic statistical calculations
        $mean = array_sum($points) / count($points);
        $median = $this->calculateMedian($points);

        // Coefficient of Variation (CV)
        $cv = $mean > 0 ? ($this->calculateStandardDeviation($points, $mean) / $mean) : null;

        // Median Absolute Deviation (MAD)
        $mad = $this->calculateMAD($points, $median);

        // Interquartile Range (IQR)
        $iqr = $this->calculateIQR($points);

        // Consistency/Boom/Bust rates vs position thresholds
        $consistencyRate = null;
        $boomRate = null;
        $bustRate = null;

        if ($thresholds) {
            $startableCount = 0;
            $boomCount = 0;
            $bustCount = 0;

            foreach ($points as $point) {
                if ($point >= $thresholds['startable']) {
                    $startableCount++;
                }
                if ($point >= $thresholds['boom']) {
                    $boomCount++;
                }
                if ($point < $thresholds['bust']) {
                    $bustCount++;
                }
            }

            $consistencyRate = ($startableCount / count($points)) * 100;
            $boomRate = ($boomCount / count($points)) * 100;
            $bustRate = ($bustCount / count($points)) * 100;
        }

        // Downside volatility (MAD for weeks below mean)
        $downsidePoints = array_filter($points, fn ($p) => $p < $mean);
        $downsideVolatility = ! empty($downsidePoints) ? $this->calculateMAD($downsidePoints, $this->calculateMedian($downsidePoints)) : null;

        // Usage volatility (coefficient of variation of snap percentages)
        $usageVolatility = null;
        if (! empty($snapPercentages)) {
            $snapMean = array_sum($snapPercentages) / count($snapPercentages);
            $usageVolatility = $snapMean > 0 ? ($this->calculateStandardDeviation($snapPercentages, $snapMean) / $snapMean) : null;
        }

        // Recency-weighted volatility (4-week rolling MAD)
        $recencyVolatility = $this->calculateRecencyVolatility($points);

        // Dashboard-friendly metrics
        $steadinessScore = $cv > 0 ? (1 / $cv) : null;
        $safeFloor = $median - $mad;
        $p90 = $this->calculatePercentile($points, 90);
        $spikeFactor = $mad > 0 ? (($p90 - $median) / $mad) : null;
        $boomBustRatio = ($bustRate > 0 && $boomRate !== null) ? ($boomRate / $bustRate) : null;

        return [
            'coefficient_of_variation' => $cv,
            'median_absolute_deviation' => $mad,
            'interquartile_range' => $iqr,
            'consistency_rate' => $consistencyRate,
            'boom_rate' => $boomRate,
            'bust_rate' => $bustRate,
            'downside_volatility' => $downsideVolatility,
            'usage_volatility' => $usageVolatility,
            'recency_volatility' => $recencyVolatility,
            'steadiness_score' => $steadinessScore,
            'safe_floor' => $safeFloor,
            'spike_factor' => $spikeFactor,
            'boom_bust_ratio' => $boomBustRatio,
        ];
    }

    /**
     * Get position-specific thresholds for consistency analysis.
     */
    private function getPositionThresholds(): ?array
    {
        $position = $this->position ?? $this->fantasy_positions[0] ?? null;

        // Position-specific weekly PPR thresholds (approximate RB/WR2 lines)
        $thresholds = [
            'QB' => ['startable' => 15.0, 'boom' => 25.0, 'bust' => 10.0],
            'RB' => ['startable' => 12.0, 'boom' => 20.0, 'bust' => 6.0],
            'WR' => ['startable' => 10.0, 'boom' => 18.0, 'bust' => 5.0],
            'TE' => ['startable' => 8.0, 'boom' => 15.0, 'bust' => 4.0],
            'K' => ['startable' => 8.0, 'boom' => 12.0, 'bust' => 5.0],
            'DEF' => ['startable' => 8.0, 'boom' => 15.0, 'bust' => 4.0],
        ];

        return $thresholds[$position] ?? null;
    }

    /**
     * Calculate median of an array.
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2.0;
        }

        return $values[$middle];
    }

    /**
     * Calculate standard deviation.
     */
    private function calculateStandardDeviation(array $values, float $mean): float
    {
        if (empty($values)) {
            return 0.0;
        }

        $variance = 0.0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }

        return sqrt($variance / count($values));
    }

    /**
     * Calculate Median Absolute Deviation (MAD).
     */
    private function calculateMAD(array $values, float $median): float
    {
        $deviations = array_map(fn ($v) => abs($v - $median), $values);

        return $this->calculateMedian($deviations);
    }

    /**
     * Calculate Interquartile Range (IQR).
     */
    private function calculateIQR(array $values): float
    {
        sort($values);
        $count = count($values);
        $q1Index = intdiv($count, 4);
        $q3Index = intdiv($count * 3, 4);

        $q1 = $values[$q1Index];
        $q3 = $values[$q3Index];

        return $q3 - $q1;
    }

    /**
     * Calculate nth percentile.
     */
    private function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $count = count($values);
        $index = ($percentile / 100) * ($count - 1);

        $lower = intdiv((int) $index, 1);
        $upper = $lower + 1;
        $weight = $index - $lower;

        if ($upper >= $count) {
            return $values[$lower];
        }

        return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
    }

    /**
     * Calculate recency-weighted volatility (4-week rolling MAD).
     */
    private function calculateRecencyVolatility(array $points): ?float
    {
        if (count($points) < 4) {
            return null;
        }

        // Get last 4 weeks (assuming points are in chronological order)
        $recentPoints = array_slice($points, -4);

        if (count($recentPoints) < 4) {
            return null;
        }

        $median = $this->calculateMedian($recentPoints);

        return $this->calculateMAD($recentPoints, $median);
    }

    /**
     * Compute PPR summary for 2024 season: totals/min/max/avg/stddev.
     */
    public function getSeason2024Summary(): array
    {
        // Prefer cached summary if available
        if ($this->relationLoaded('seasonSummaries')) {
            $cached = $this->getRelation('seasonSummaries')->firstWhere('season', 2024);
        } else {
            $cached = $this->seasonSummaries()->where('season', 2024)->first();
        }

        if ($cached) {
            return [
                'total_points' => $cached->total_points,
                'min_points' => $cached->min_points,
                'max_points' => $cached->max_points,
                'average_points_per_game' => $cached->average_points_per_game,
                'stddev_below' => $cached->stddev_below,
                'stddev_above' => $cached->stddev_above,
                'games_active' => (int) $cached->games_active,
                'snap_percentage_avg' => $cached->snap_percentage_avg,
                'position_rank' => $cached->position_rank,
                'volatility' => is_array($cached->volatility) ? $cached->volatility : [],
            ];
        }

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

            // Collect snap percentage data from multiple possible schemas
            if (isset($stats['snap_pct']) && is_numeric($stats['snap_pct'])) {
                // Already a percent 0-100
                $snapData[] = (float) $stats['snap_pct'];
            } elseif (
                isset($stats['off_snp']) && isset($stats['tm_off_snp']) &&
                is_numeric($stats['off_snp']) && is_numeric($stats['tm_off_snp']) && (float) $stats['tm_off_snp'] > 0
            ) {
                // Convert snaps / team snaps to percent
                $snapData[] = ((float) $stats['off_snp'] / (float) $stats['tm_off_snp']) * 100.0;
            } elseif (isset($stats['snap_share']) && is_numeric($stats['snap_share'])) {
                // Some providers send a 0-1 share
                $snapData[] = ((float) $stats['snap_share']) * 100.0;
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

        // Get volatility metrics
        $volatilityMetrics = $this->getVolatilityMetrics();

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
            'volatility' => $volatilityMetrics,
        ];
    }

    /**
     * Cached 2024 target share average if present.
     */
    public function getSeason2024AverageTargetShare(): ?float
    {
        // Deprecated: prefer using cached value on PlayerSeasonSummary.target_share_avg
        return null;
    }
}
