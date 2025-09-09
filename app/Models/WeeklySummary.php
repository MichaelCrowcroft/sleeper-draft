<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WeeklySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'league_id',
        'year',
        'week',
        'content',
        'generated_at',
        'prompt_used',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'year' => 'integer',
        'week' => 'integer',
    ];

    /**
     * Scope to find a specific weekly summary
     */
    public function scopeForLeagueWeek($query, string $leagueId, int $year, int $week)
    {
        return $query->where('league_id', $leagueId)
                    ->where('year', $year)
                    ->where('week', $week);
    }

    /**
     * Get or create a weekly summary for the given league, year, and week
     */
    public static function getOrCreate(string $leagueId, int $year, int $week): self
    {
        return static::firstOrCreate([
            'league_id' => $leagueId,
            'year' => $year,
            'week' => $week,
        ]);
    }

    /**
     * Check if the summary is recent (within last 24 hours)
     */
    public function isRecent(): bool
    {
        return $this->generated_at && $this->generated_at->isAfter(now()->subDay());
    }

    /**
     * Mark the summary as generated now
     */
    public function markGenerated(string $content, string $prompt): void
    {
        $this->update([
            'content' => $content,
            'prompt_used' => $prompt,
            'generated_at' => now(),
        ]);
    }
}
