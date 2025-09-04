<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStats extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'season' => 'integer',
        'week' => 'integer',
        'game_date' => 'date',
        'date' => 'datetime',
        'updated_at_ms' => 'integer',
        'last_modified_ms' => 'integer',
        'stats' => 'array',
        'raw' => 'array',
    ];

    /**
     * Get the player that owns these stats.
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'player_id');
    }

    /**
     * Scope to filter by season.
     */
    public function scopeSeason($query, int $season)
    {
        return $query->where('season', $season);
    }

    /**
     * Scope to filter by week.
     */
    public function scopeWeek($query, int $week)
    {
        return $query->where('week', $week);
    }

    /**
     * Scope to filter by season type.
     */
    public function scopeSeasonType($query, string $seasonType)
    {
        return $query->where('season_type', $seasonType);
    }

    /**
     * Scope to filter by team.
     */
    public function scopeTeam($query, string $team)
    {
        return $query->where('team', $team);
    }

    /**
     * Scope to get stats for a specific player and season.
     */
    public function scopeForPlayer($query, string $sleeperPlayerId, int $season)
    {
        return $query->where('player_id', $sleeperPlayerId)
            ->where('season', $season)
            ->orderBy('week');
    }
}
