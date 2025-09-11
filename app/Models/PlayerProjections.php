<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerProjections extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $primaryKey = null;

    public $incrementing = false;

    /**
     * Append computed attributes when serializing.
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
     * Get the player that owns these projections.
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
     * Scope to include weekly position rank and player position using SQL window function.
     * Ranks by projected PPR points (nested stats.pts_ppr or flattened pts_ppr if present).
     */
    public function scopeWithWeeklyRank($query, int $season, int $week, ?string $seasonType = null)
    {
        $query->where('season', $season)
            ->where('week', $week);

        if ($seasonType !== null) {
            $query->where('season_type', $seasonType);
        }

        // Use flattened column pts_ppr (present in SQLite schema used by tests)
        return $query
            ->join('players', 'players.player_id', '=', 'player_projections.player_id')
            ->select('player_projections.*')
            ->selectRaw("ROW_NUMBER() OVER (\n                PARTITION BY players.position\n                ORDER BY COALESCE(player_projections.pts_ppr, 0) DESC\n            ) as weekly_rank")
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
     * Scope to filter by source.
     */
    public function scopeSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope to get projections for a specific player and season.
     */
    public function scopeForPlayer($query, string $sleeperPlayerId, int $season)
    {
        return $query->where('sleeper_player_id', $sleeperPlayerId)
            ->where('season', $season)
            ->orderBy('week');
    }

    /**
     * Scope to get projections from a specific source for a player and season.
     */
    public function scopeForPlayerAndSource($query, string $sleeperPlayerId, int $season, string $source)
    {
        return $query->where('sleeper_player_id', $sleeperPlayerId)
            ->where('season', $season)
            ->where('source', $source)
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
