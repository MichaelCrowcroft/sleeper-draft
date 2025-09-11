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
     * Scope: filter by single position (case-insensitive) when provided.
     */
    public function scopePosition(Builder $query, ?string $position): Builder
    {
        $pos = strtoupper((string) ($position ?? ''));

        return $pos !== ''
            ? $query->where('position', $pos)
            : $query;
    }

    /**
     * Scope: filter by team (case-insensitive) when provided.
     */
    public function scopeTeam(Builder $query, ?string $team): Builder
    {
        $tm = strtoupper((string) ($team ?? ''));

        return $tm !== ''
            ? $query->where('team', $tm)
            : $query;
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
     * Scope: exclude players by their external player_id values.
     *
     * @param  array<int, string|int>  $ids
     */
    public function scopeExcludePlayerIds(Builder $query, array $ids): Builder
    {
        return ! empty($ids)
            ? $query->whereNotIn('player_id', $ids)
            : $query;
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
}
