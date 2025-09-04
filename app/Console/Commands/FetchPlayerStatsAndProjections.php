<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Models\PlayerStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchPlayerStatsAndProjections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-player-stats-and-projections
                            {--season= : The season year to fetch stats for (defaults to current year)}
                            {--player-id= : Specific player ID to fetch stats for (for testing)}
                            {--chunk-size=50 : Number of players to process at once}
                            {--limit= : Limit the number of players to process for testing}
                            {--dry-run : Show what would be done without actually fetching data}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch weekly player stats from Sleeper API and store in database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $season = $this->option('season') ?: now()->year;
        $playerId = $this->option('player-id');
        $chunkSize = (int) $this->option('chunk-size');
        $limit = $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info("Fetching player stats for season {$season}");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be saved');
        }

        // Get players query
        $playersQuery = Player::where('sport', 'nfl')
            ->whereNotNull('player_id')
            ->where('active', true);

        if ($playerId) {
            $playersQuery->where('player_id', $playerId);
            $this->info("Fetching stats for specific player: {$playerId}");
        } elseif ($limit) {
            $playersQuery->limit((int) $limit);
        }

        $totalPlayers = $playersQuery->count();
        $this->info("Found {$totalPlayers} active NFL players to process");

        if ($totalPlayers === 0) {
            $this->warn('No players found to process');

            return;
        }

        $bar = $this->output->createProgressBar($totalPlayers);
        $bar->start();

        $statsFetched = 0;
        $errors = 0;

        $playersQuery->chunk($chunkSize, function ($players) use ($season, $dryRun, &$statsFetched, &$errors, $bar) {
            foreach ($players as $player) {
                try {
                    $result = $this->fetchPlayerStats($player, $season, $dryRun);

                    if ($result) {
                        $statsFetched += $result;
                    }
                } catch (\Exception $e) {
                    $errors++;
                    Log::error("Failed to fetch stats for player {$player->player_id}: {$e->getMessage()}");
                    $this->newLine();
                    $this->error("Error fetching stats for player {$player->full_name} ({$player->player_id}): {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Completed! Stats fetched: {$statsFetched}, Errors: {$errors}");

        if ($errors > 0) {
            $this->warn("{$errors} players had errors. Check logs for details.");
        }
    }

    /**
     * Fetch stats for a single player.
     */
    private function fetchPlayerStats(Player $player, int $season, bool $dryRun = false): int
    {
        $url = "https://api.sleeper.com/stats/nfl/player/{$player->player_id}?season_type=regular&season={$season}&grouping=week";

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                throw new \Exception("API returned status {$response->status()}");
            }

            $weeklyStats = $response->json();

            if (empty($weeklyStats)) {
                return 0; // No stats available for this player/season
            }

            $statsSaved = 0;

            foreach ($weeklyStats as $week => $weekData) {
                if ($dryRun) {
                    $statsSaved++;

                    continue;
                }

                // Skip null weeks (player didn't play)
                if ($weekData === null) {
                    continue;
                }

                $statsSaved += $this->savePlayerStats($player, $season, $week, $weekData);
            }

            return $statsSaved;

        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch from API: {$e->getMessage()}");
        }
    }

    /**
     * Save player stats for a specific week.
     */
    private function savePlayerStats(Player $player, int $season, string $week, array $weekData): int
    {
        try {
            // Extract metadata
            $metadata = $weekData;
            unset($metadata['stats']); // Remove stats from metadata

            // Flatten stats array
            $stats = $weekData['stats'] ?? [];

            // Prepare data for database
            $data = [
                'player_id' => $player->player_id, // Use player_id as varchar foreign key
                'sport' => 'nfl',
                'season' => (string) $season, // Schema shows season as varchar
                'week' => (int) $week,
                'season_type' => $metadata['season_type'] ?? 'regular',
                'date' => isset($metadata['date']) ? date('Y-m-d', strtotime($metadata['date'])) : null,
                'team' => $metadata['team'] ?? null,
                'opponent' => $metadata['opponent'] ?? null,
                'game_id' => $metadata['game_id'] ?? null,
                'company' => $metadata['company'] ?? 'sportradar',
                'updated_at_ms' => isset($metadata['updated_at']) ? (int) $metadata['updated_at'] : null,
                'last_modified_ms' => isset($metadata['last_modified']) ? (int) $metadata['last_modified'] : null,
                'raw' => json_encode($weekData),
            ];

            // Validate required fields for MySQL compatibility
            if (empty($data['player_id']) || empty($data['season'])) {
                Log::error('Invalid data for MySQL - missing required fields', [
                    'player_id' => $data['player_id'],
                    'season' => $data['season'],
                    'week' => $data['week'],
                ]);

                return 0;
            }

            // Add all stats as flattened columns
            $data = array_merge($data, $this->flattenStats($stats));

            // Use updateOrCreate to handle duplicates
            $saved = PlayerStats::updateOrCreate(
                [
                    'player_id' => $player->player_id,
                    'season' => (string) $season,
                    'week' => (int) $week,
                    'season_type' => $data['season_type'],
                    'company' => $data['company'],
                    'sport' => $data['sport'],
                ],
                $data
            );

            if (! $saved || ! $saved->exists) {
                Log::error('Failed to save stats - record not created', [
                    'player_id' => $player->player_id,
                    'season' => $season,
                    'week' => $week,
                    'data' => $data,
                ]);

                return 0;
            }

            return 1;

        } catch (\Exception $e) {
            Log::error("Failed to save stats for player {$player->player_id}, season {$season}, week {$week}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data_keys' => isset($data) ? array_keys($data) : 'data not set',
            ]);

            return 0;
        }
    }

    /**
     * Flatten the stats array into individual columns.
     */
    private function flattenStats(array $stats): array
    {
        $flattened = [];

        foreach ($stats as $statKey => $statValue) {
            // Convert stat keys to snake_case column names
            $columnName = $this->statKeyToColumn($statKey);

            // Ensure the column exists in our migration
            if ($this->isValidStatColumn($columnName)) {
                $flattened[$columnName] = $this->normalizeStatValue($statValue);
            }
        }

        return $flattened;
    }

    /**
     * Convert stat key to database column name.
     */
    private function statKeyToColumn(string $statKey): string
    {
        // Convert camelCase and other formats to snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $statKey));
    }

    /**
     * Check if a column name is valid for our stats table.
     */
    private function isValidStatColumn(string $column): bool
    {
        $validColumns = [
            'pts_half_ppr', 'pts_ppr', 'pts_std',
            'pos_rank_half_ppr', 'pos_rank_ppr', 'pos_rank_std',
            'gp', 'gs', 'gms_active',
            'off_snp', 'tm_off_snp', 'tm_def_snp', 'tm_st_snp',
            'rec', 'rec_tgt', 'rec_yd', 'rec_td', 'rec_fd', 'rec_air_yd', 'rec_rz_tgt', 'rec_lng',
            'rush_att', 'rush_yd', 'rush_td', 'fum', 'fum_lost',
        ];

        return in_array($column, $validColumns);
    }

    /**
     * Normalize stat values to ensure they're database-friendly.
     * MySQL can be more strict about data types than SQLite.
     */
    private function normalizeStatValue($value)
    {
        if ($value === null) {
            return null;
        }

        // Handle empty strings and convert to null for MySQL compatibility
        if ($value === '' || $value === 'null') {
            return null;
        }

        // Convert to float if it's a number, but ensure it's a valid float
        if (is_numeric($value)) {
            $floatValue = (float) $value;
            // Check if the float conversion resulted in a valid number
            if (is_finite($floatValue)) {
                return $floatValue;
            }
        }

        return $value;
    }
}
