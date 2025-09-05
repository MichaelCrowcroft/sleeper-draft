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
        }

        if (empty($points)) {
            return [
                'total_points' => 0.0,
                'min_points' => 0.0,
                'max_points' => 0.0,
                'average_points_per_game' => 0.0,
                'stddev_below' => 0.0,
                'stddev_above' => 0.0,
                'games_active' => 0,
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

        return [
            'total_points' => $total,
            'min_points' => $min,
            'max_points' => $max,
            'average_points_per_game' => $avg,
            'stddev_below' => $avg - $stddev,
            'stddev_above' => $avg + $stddev,
            'games_active' => $games,
        ];
    }
}
