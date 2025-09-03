<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerProjections extends Model
{
    protected $fillable = [
        'player_id',
        'sport',
        'season',
        'week',
        'season_type',
        'date',
        'team',
        'opponent',
        'game_id',
        'company',
        'raw',
        'updated_at_ms',
        'last_modified_ms',
        'pts_half_ppr',
        'pts_ppr',
        'pts_std',
        'adp_dd_ppr',
        'pos_adp_dd_ppr',
        'rec',
        'rec_tgt',
        'rec_yd',
        'rec_td',
        'rec_fd',
        'rush_att',
        'rush_yd',
        'fum',
        'fum_lost',
    ];

    protected $casts = [
        'season' => 'integer',
        'week' => 'integer',
        'date' => 'date',
        'updated_at_ms' => 'integer',
        'last_modified_ms' => 'integer',
        'pts_half_ppr' => 'decimal:2',
        'pts_ppr' => 'decimal:2',
        'pts_std' => 'decimal:2',
        'adp_dd_ppr' => 'integer',
        'pos_adp_dd_ppr' => 'integer',
        'rec' => 'decimal:1',
        'rec_tgt' => 'decimal:1',
        'rec_yd' => 'decimal:1',
        'rec_td' => 'decimal:1',
        'rec_fd' => 'decimal:1',
        'rush_att' => 'decimal:1',
        'rush_yd' => 'decimal:1',
        'fum' => 'decimal:1',
        'fum_lost' => 'decimal:1',
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
}
