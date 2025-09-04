<?php

namespace App\Console\Commands;

use App\Models\PlayerStats;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class UpdatePlayerStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sleeper:player:stats
                           {player_id : The Sleeper player ID (e.g., 6794)}
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
        $playerId = (string) $this->argument('player_id');
        $season = (string) $this->option('season');
        $seasonType = (string) $this->option('season-type');

        $response = Http::get("https://api.sleeper.com/stats/nfl/player/{$playerId}", [
            'season_type' => $seasonType,
            'season' => $season,
            'grouping' => 'week',
        ]);

        if (! $response->successful()) {
            $this->error('Failed to fetch data from Sleeper: HTTP '.$response->status());

            return;
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            $this->error('Unexpected API response shape.');

            return;
        }

        $records = [];

        foreach ($payload as $week => $weekData) {
            if (! is_numeric($week) || ! is_array($weekData)) {
                continue;
            }

            $records[] = $this->mapWeekToDatabaseRow(
                $playerId,
                (int) $season,
                (int) $week,
                $seasonType,
                $weekData
            );
        }

        if (empty($records)) {
            $this->warn('No weekly records to upsert.');

            return;
        }

        PlayerStats::upsert(
            $records,
            ['player_id', 'season', 'week', 'season_type'],
            [
                'sport', 'game_date', 'date', 'team', 'opponent', 'game_id', 'company',
                'updated_at_ms', 'last_modified_ms', 'stats', 'raw',
            ]
        );

        $this->info('Upserted '.count($records).' weekly player stat records for player '.$playerId);
    }

    /**
     * Upsert player stats data to the database.
     */
    /**
     * Map a weekly API payload into a database row that matches the schema.
     */
    private function mapWeekToDatabaseRow(string $playerId, int $season, int $week, string $seasonType, array $weekData): array
    {
        $dateString = $weekData['date'] ?? null;
        $date = null;
        $gameDate = null;

        if (is_string($dateString) && $dateString !== '') {
            try {
                $date = Carbon::parse($dateString);
                $gameDate = $date->toDateString();
            } catch (\Throwable $e) {
                // Ignore parse errors, keep nulls
            }
        }

        $opponent = $weekData['opponent'] ?? ($weekData['opponent_team'] ?? null);
        $statsPayload = $weekData['stats'] ?? $weekData; // Some responses embed stats directly

        return [
            'player_id' => $playerId,
            'sport' => $weekData['sport'] ?? 'nfl',
            'season' => $season,
            'week' => $week,
            'season_type' => $seasonType,
            'game_date' => $gameDate,
            'date' => $date,
            'team' => $weekData['team'] ?? null,
            'opponent' => $opponent,
            'game_id' => $weekData['game_id'] ?? null,
            'company' => $weekData['company'] ?? 'sportradar',
            'updated_at_ms' => isset($weekData['updated_at']) ? (int) $weekData['updated_at'] : null,
            'last_modified_ms' => isset($weekData['last_modified']) ? (int) $weekData['last_modified'] : null,
            'stats' => is_array($statsPayload) ? $statsPayload : null,
            'raw' => $weekData,
        ];
    }
}
