<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Models\PlayerProjections;
use App\Models\PlayerStats;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class FetchPlayerStatsAndProjections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-player-stats-and-projections {--season=} {--sport=nfl} {--season-type=regular} {--max-per-minute=250}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch weekly player stats and projections for a season and store them in the database.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $season = (string) ($this->option('season') ?: now()->year);
        $sport = (string) $this->option('sport');
        $seasonType = (string) $this->option('season-type');

        $this->info("Fetching weekly stats & projections for season {$season} ({$seasonType})...");
        $maxPerMinute = (int) $this->option('max-per-minute');

        $playersQuery = Player::query()->where('sport', $sport);
        $bar = $this->output->createProgressBar($playersQuery->count());
        $bar->start();

        $playersQuery->chunkById(250, function ($players) use ($season, $sport, $seasonType, $bar, $maxPerMinute) {
            $chunkStartedAt = microtime(true);
            foreach ($players as $player) {
                try {
                    $this->fetchAndStoreForPlayer($player, $season, $sport, $seasonType);
                } catch (\Throwable $e) {
                    $this->warn("Failed for {$player->player_id}: {$e->getMessage()}");
                } finally {
                    $bar->advance();
                }
            }
            $this->enforceRateLimitForChunk((int) $players->count(), $chunkStartedAt, $maxPerMinute);
        }, 'id');

        $bar->finish();
        $this->newLine();
        $this->info('Done.');
    }

    protected function enforceRateLimitForChunk(int $processedInChunk, float $chunkStartedAt, int $maxPerMinute): void
    {
        // Only enforce when a full chunk (equal to limit) is processed.
        if ($processedInChunk < $maxPerMinute) {
            return;
        }

        $elapsed = microtime(true) - $chunkStartedAt;
        $window = 60.0;
        if ($elapsed < $window) {
            $remaining = (int) floor(($window - $elapsed) * 1_000_000);
            if ($remaining > 0) {
                usleep($remaining);
            }
        }
    }

    protected function fetchAndStoreForPlayer(Player $player, string $season, string $sport, string $seasonType): void
    {
        // Stats (weekly)
        $statsResponse = Sleeper::players()->stats(
            playerId: (string) $player->player_id,
            season: $season,
            sport: $sport,
            seasonType: $seasonType,
            grouping: 'week'
        );

        $stats = $statsResponse->json();
        if (is_array($stats)) {
            foreach ($stats as $weekKey => $payload) {
                if (! is_array($payload)) {
                    continue;
                }
                $week = $this->determineWeek($weekKey, $payload);
                if ($week === null) {
                    continue; // not a weekly payload, likely season summary/ranks
                }
                $this->upsertStats($player, $week, $payload, $season, $sport, $seasonType);
            }
        }

        // Projections (weekly)
        $projectionsResponse = Sleeper::players()->projections(
            playerId: (string) $player->player_id,
            season: $season,
            sport: $sport,
            seasonType: $seasonType,
            grouping: 'week'
        );

        $projections = $projectionsResponse->json();
        if (is_array($projections)) {
            foreach ($projections as $weekKey => $payload) {
                if (! is_array($payload)) {
                    continue;
                }
                $week = $this->determineWeek($weekKey, $payload);
                if ($week === null) {
                    continue;
                }
                $this->upsertProjections($player, $week, $payload, $season, $sport, $seasonType);
            }
        }
    }

    protected function upsertStats(Player $player, int $week, array $payload, string $season, string $sport, string $seasonType): void
    {
        $attributes = [
            'player_id' => (string) $player->player_id,
            'season' => $season,
            'week' => $week,
            'season_type' => $seasonType,
            'sport' => $sport,
            'company' => $payload['company'] ?? ($payload['category'] ?? null),
        ];

        $values = [
            'date' => isset($payload['date']) ? Carbon::parse($payload['date'])->toDateString() : null,
            'team' => $payload['team'] ?? $player->team,
            'opponent' => $payload['opponent'] ?? null,
            'game_id' => $payload['game_id'] ?? null,
            'stats' => $payload['stats'] ?? [],
            'raw' => $payload,
            'updated_at_ms' => $payload['updated_at'] ?? null,
            'last_modified_ms' => $payload['last_modified'] ?? null,
        ];

        PlayerStats::updateOrCreate($attributes, $values);
    }

    protected function upsertProjections(Player $player, int $week, array $payload, string $season, string $sport, string $seasonType): void
    {
        $attributes = [
            'player_id' => (string) $player->player_id,
            'season' => $season,
            'week' => $week,
            'season_type' => $seasonType,
            'sport' => $sport,
            'company' => $payload['company'] ?? ($payload['category'] ?? null),
        ];

        $values = [
            'date' => isset($payload['date']) ? Carbon::parse($payload['date'])->toDateString() : null,
            'team' => $payload['team'] ?? $player->team,
            'opponent' => $payload['opponent'] ?? null,
            'game_id' => $payload['game_id'] ?? null,
            'stats' => $payload['stats'] ?? [],
            'raw' => $payload,
            'updated_at_ms' => $payload['updated_at'] ?? null,
            'last_modified_ms' => $payload['last_modified'] ?? null,
        ];

        PlayerProjections::updateOrCreate($attributes, $values);
    }

    protected function determineWeek(int|string $weekKey, array $payload): ?int
    {
        $candidate = $payload['week'] ?? $weekKey;
        if (! is_numeric($candidate)) {
            return null;
        }

        $week = (int) $candidate;
        if ($week < 1 || $week > 25) { // NFL weeks range guard
            return null;
        }

        return $week;
    }
}
