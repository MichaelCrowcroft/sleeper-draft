<?php

namespace App\Console\Commands;

use App\Models\PlayerProjections;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdatePlayerProjections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sleeper:player:projections
                           {player-id : The Sleeper player ID (e.g., 6794)}
                           {--season=2025 : The season year (default: current year 2024)}
                           {--season-type=regular : Season type (regular, postseason)}
                           ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch player projections from the Sleeper API';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $playerId = (string) $this->argument('player-id');
        $season = (string) $this->option('season');
        $seasonType = (string) $this->option('season-type');

        $response = Http::get("https://api.sleeper.com/projections/nfl/player/{$playerId}", [
            'season_type' => $seasonType,
            'season' => $season,
            'grouping' => 'week',
        ]);

        $weeks = $response->json();

        foreach ($weeks as $week) {
            // Skip null entries (some weeks may not have projections)
            if ($week === null) {
                continue;
            }

            $attributes = [
                'player_id' => $playerId,
                'season' => $week['season'],
                'week' => $week['week'],
                'season_type' => $seasonType,
            ];
            $values = [
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

            PlayerProjections::updateOrCreate($attributes, $values);
        }
    }
}
