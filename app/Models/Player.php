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
}
