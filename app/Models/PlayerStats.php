<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStats extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'week' => 'integer',
            'updated_at_ms' => 'integer',
            'last_modified_ms' => 'integer',
            'date' => 'date',
            'pts_half_ppr' => 'float',
            'pts_ppr' => 'float',
            'pts_std' => 'float',
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
            'rec' => 'float',
            'rec_tgt' => 'float',
            'rec_yd' => 'float',
            'rec_td' => 'float',
            'rec_fd' => 'float',
            'rec_air_yd' => 'float',
            'rec_rz_tgt' => 'float',
            'rec_lng' => 'integer',
            'rush_att' => 'float',
            'rush_yd' => 'float',
            'rush_td' => 'float',
            'fum' => 'float',
            'fum_lost' => 'float',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'player_id');
    }
}
