<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStats extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $primaryKey = null;

    public $incrementing = false;

    // Ranking is stored in weekly_ranking; no computed weekly_rank attributes

    protected $casts = [
        'season' => 'integer',
        'week' => 'integer',
        'game_date' => 'date',
        'date' => 'datetime',
        'updated_at_ms' => 'integer',
        'last_modified_ms' => 'integer',
        'stats' => 'array',
        'raw' => 'array',
        'weekly_ranking' => 'integer',
    ];

    /**
     * Get the player that owns these stats.
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'player_id');
    }

    // No weekly rank accessor or player_position computed attribute

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

    /**
     * Custom updateOrCreate for composite primary key.
     */
    public static function updateOrCreate(array $attributes, array $values = [])
    {
        $instance = static::where($attributes)->first();

        if ($instance) {
            $instance->update($values);

            return $instance;
        }

        return static::create(array_merge($attributes, $values));
    }

    /**
     * Override setKeysForSaveQuery to handle composite primary key.
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = ['player_id', 'season', 'week', 'season_type'];

        foreach ($keys as $key) {
            $query->where($key, '=', $this->getAttribute($key));
        }

        return $query;
    }
}
