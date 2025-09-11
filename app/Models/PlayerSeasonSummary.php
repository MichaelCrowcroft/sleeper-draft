<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerSeasonSummary extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'season' => 'integer',
        'total_points' => 'float',
        'min_points' => 'float',
        'max_points' => 'float',
        'average_points_per_game' => 'float',
        'stddev_below' => 'float',
        'stddev_above' => 'float',
        'games_active' => 'integer',
        'snap_percentage_avg' => 'float',
        'position_rank' => 'integer',
        'target_share_avg' => 'float',
        'volatility' => 'array',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'player_id');
    }
}
