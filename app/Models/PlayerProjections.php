<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerProjections extends Model
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
            'adp_dd_ppr' => 'integer',
            'pos_adp_dd_ppr' => 'integer',
            'rec' => 'float',
            'rec_tgt' => 'float',
            'rec_yd' => 'float',
            'rec_td' => 'float',
            'rec_fd' => 'float',
            'rush_att' => 'float',
            'rush_yd' => 'float',
            'fum' => 'float',
            'fum_lost' => 'float',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'player_id');
    }
}
