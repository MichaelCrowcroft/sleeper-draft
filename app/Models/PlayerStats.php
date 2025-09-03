<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStats extends Model
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
        'pos_rank_half_ppr',
        'pos_rank_ppr',
        'pos_rank_std',
        'gp',
        'gs',
        'gms_active',
        'off_snp',
        'tm_off_snp',
        'tm_def_snp',
        'tm_st_snp',
        'rec',
        'rec_tgt',
        'rec_yd',
        'rec_td',
        'rec_fd',
        'rec_air_yd',
        'rec_rz_tgt',
        'rec_lng',
        'rush_att',
        'rush_yd',
        'rush_td',
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
        'pos_rank_half_ppr' => 'integer',
        'pos_rank_ppr' => 'integer',
        'pos_rank_std' => 'integer',
        'gp' => 'integer',
        'gs' => 'integer',
        'gms_active' => 'integer',
        'off_snp' => 'integer',
        'tm_off_snp' => 'integer',
        'tm_def_snp' => 'integer',
        'tm_st_snp' => 'integer',
        'rec' => 'decimal:1',
        'rec_tgt' => 'decimal:1',
        'rec_yd' => 'decimal:1',
        'rec_td' => 'decimal:1',
        'rec_fd' => 'decimal:1',
        'rec_air_yd' => 'decimal:1',
        'rec_rz_tgt' => 'decimal:1',
        'rec_lng' => 'integer',
        'rush_att' => 'decimal:1',
        'rush_yd' => 'decimal:1',
        'rush_td' => 'decimal:1',
        'fum' => 'decimal:1',
        'fum_lost' => 'decimal:1',
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
        return $query->where('sleeper_player_id', $sleeperPlayerId)
            ->where('season', $season)
            ->orderBy('week');
    }
}
