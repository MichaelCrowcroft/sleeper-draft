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

    /**
     * Append computed attributes when serializing.
     * - weekly_rank: computed via scopeWithWeeklyRank() selection
     * - player_position: joined from players table or relation fallback
     */
    protected $appends = ['weekly_rank', 'player_position'];

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
     * Scope to include weekly position rank and player position using SQL window function.
     * Uses PPR points from the JSON stats payload.
     */
    public function scopeWithWeeklyRank($query, int $season, int $week, ?string $seasonType = null)
    {
        $query->where('season', $season)
            ->where('week', $week);

        if ($seasonType !== null) {
            $query->where('season_type', $seasonType);
        }

        return $query
            ->join('players', 'players.player_id', '=', 'player_stats.player_id')
            ->select('player_stats.*')
            ->selectRaw("ROW_NUMBER() OVER (\n                PARTITION BY players.position\n                ORDER BY COALESCE(CAST(json_extract(player_stats.stats, '$.pts_ppr') AS REAL), 0) DESC\n            ) as weekly_rank")
            ->selectRaw('players.position as player_position');
    }

    /**
     * Accessor for the computed weekly_rank column when selected via scope.
     */
    public function getWeeklyRankAttribute(): ?int
    {
        return isset($this->attributes['weekly_rank'])
            ? (int) $this->attributes['weekly_rank']
            : null;
    }

    /**
     * Accessor for the joined player position. Falls back to relation when available.
     */
    public function getPlayerPositionAttribute(): ?string
    {
        if (array_key_exists('player_position', $this->attributes)) {
            return $this->attributes['player_position'];
        }

        if ($this->relationLoaded('player') && $this->player) {
            return $this->player->position;
        }

        return null;
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
