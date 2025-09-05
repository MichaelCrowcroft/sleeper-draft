<?php

namespace App\Console\Commands;

use App\Models\PlayerStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdatePlayerStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sleeper:player:stats
                           {player-id : The Sleeper player ID (e.g., 6794)}
                           {--season=2025 : The season year (default: 2025)}
                           {--season-type=regular : Season type (regular, postseason)}
                           ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch player rankings/stats from the Sleeper API';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $playerId = (string) $this->argument('player-id');
        $season = (string) $this->option('season');
        $seasonType = (string) $this->option('season-type');

        $response = Http::get("https://api.sleeper.com/stats/nfl/player/{$playerId}", [
            'season_type' => $seasonType,
            'season' => $season,
            'grouping' => 'week',
        ]);

        if (! $response->successful()) {
            $this->error("Failed to fetch player stats from Sleeper API. HTTP {$response->status()}");

            return;
        }

        $weeks = $response->json();

        if (empty($weeks)) {
            $this->info("No stats found for player {$playerId} in {$season} {$seasonType} season");

            return;
        }

        $this->info('Found '.count($weeks)." weeks of stats for player {$playerId}");

        foreach ($weeks as $week) {
            if (empty($week) || ! isset($week['season'], $week['week'])) {
                $this->warn('Skipping incomplete week data');

                continue;
            }
            $attributes = [
                'player_id' => $playerId,
                'season' => $week['season'],
                'week' => $week['week'],
            ];
            $values = [
                'season_type' => $seasonType,
                'game_date' => $week['date'],
                'sport' => $week['sport'],
                'company' => $week['company'],
                'team' => $week['team'],
                'opponent' => $week['opponent'],
                'game_id' => $week['game_id'],
                'updated_at_ms' => $week['updated_at'],
                'last_modified_ms' => $week['last_modified'],
                'stats' => $week['stats'],
                'raw' => $week,
            ];

            PlayerStats::updateOrCreate($attributes, $values);
            $this->line("Stored stats for week {$week['week']}");
        }

        $this->info("✅ Successfully updated stats for player {$playerId}");
    }
}
