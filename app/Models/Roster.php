<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Roster extends Model
{
    protected $fillable = [
        'user_id',
        'league_id',
        'sleeper_roster_id',
        'owner_id',
        'wins',
        'losses',
        'ties',
        'fpts',
        'fpts_decimal',
        'players',
        'metadata',
    ];

    protected $casts = [
        'players' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }
}
