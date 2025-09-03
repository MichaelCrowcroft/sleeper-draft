<?php

namespace App\Jobs;

use App\Models\Player;
use App\Models\PlayerProjections;
use App\Models\PlayerStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class ProcessPlayerStatsAndProjectionsChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $playerIds,
        public string $season,
        public string $sport = 'nfl',
        public string $seasonType = 'regular',
        public int $maxPerMinute = 250 // Back to higher rate since each player now makes only 2 requests (1 for stats, 1 for projections)
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $chunkStartedAt = microtime(true);

        \Illuminate\Support\Facades\Log::info("Processing chunk with players: " . implode(', ', $this->playerIds));

        foreach ($this->playerIds as $playerId) {
            $player = Player::where('player_id', (string) $playerId)->first();
            if (! $player) {
                \Illuminate\Support\Facades\Log::warning("Player not found: $playerId");
                continue;
            }

            \Illuminate\Support\Facades\Log::info("Processing player: {$player->player_id} ({$player->first_name} {$player->last_name})");

            try {
                $this->fetchAndStoreForPlayer($player, $this->season, $this->sport, $this->seasonType);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Error processing player {$player->player_id}: " . $e->getMessage());
                // Swallow per-player errors; job continues
            }
        }

        $this->enforceRateLimitForChunk(count($this->playerIds), $chunkStartedAt, $this->maxPerMinute);
    }

    protected function fetchAndStoreForPlayer(Player $player, string $season, string $sport, string $seasonType): void
    {
        // Fetch weekly stats data using grouping=week parameter
        $statsResponse = Sleeper::players()->stats((string) $player->player_id, $season, $sport, $seasonType, 'week');
        $stats = $statsResponse->json();

        if (is_array($stats) && !empty($stats)) {
            // With grouping=week, the response should be keyed by week numbers
            foreach ($stats as $weekKey => $weekData) {
                if (!is_numeric($weekKey) || !is_array($weekData)) {
                    continue;
                }
                $week = (int) $weekKey;
                if ($week < 1 || $week > 25) {
                    continue;
                }
                $this->upsertStats($player, $week, $weekData, $season, $sport, $seasonType);
            }
        }

        // Fetch weekly projections data using grouping=week parameter
        $projectionsResponse = Sleeper::players()->projections((string) $player->player_id, $season, $sport, $seasonType, 'week');
        $projections = $projectionsResponse->json();

        if (is_array($projections) && !empty($projections)) {
            // With grouping=week, the response should be keyed by week numbers
            foreach ($projections as $weekKey => $weekData) {
                if (!is_numeric($weekKey) || !is_array($weekData)) {
                    continue;
                }
                $week = (int) $weekKey;
                if ($week < 1 || $week > 25) {
                    continue;
                }
                $this->upsertProjections($player, $week, $weekData, $season, $sport, $seasonType);
            }
        }
    }

    protected function upsertStats(Player $player, int $week, array $payload, string $season, string $sport, string $seasonType): void
    {
        // For weekly data, the stats are nested under 'stats' key
        $stats = $payload['stats'] ?? $payload;

        PlayerStats::updateOrCreate([
            'player_id' => (string) $player->player_id,
            'season' => $season,
            'week' => $week,
            'season_type' => $seasonType,
            'sport' => $sport,
            'company' => $payload['company'] ?? ($payload['category'] ?? null),
        ], [
            'date' => isset($payload['date']) ? Carbon::parse($payload['date'])->toDateString() : null,
            'team' => $payload['team'] ?? $player->team,
            'opponent' => $payload['opponent'] ?? null,
            'game_id' => $payload['game_id'] ?? null,
            'raw' => $payload,
            'updated_at_ms' => $payload['updated_at'] ?? null,
            'last_modified_ms' => $payload['last_modified'] ?? null,
            // Map ranking data to database columns (Sleeper API returns rankings, not actual stats)
            'pts_half_ppr' => self::num($stats['rank_half_ppr'] ?? null),
            'pts_ppr' => self::num($stats['rank_ppr'] ?? null),
            'pts_std' => self::num($stats['rank_std'] ?? null),
            'pos_rank_half_ppr' => self::num($stats['pos_rank_half_ppr'] ?? null),
            'pos_rank_ppr' => self::num($stats['pos_rank_ppr'] ?? null),
            'pos_rank_std' => self::num($stats['pos_rank_std'] ?? null),
            'gp' => self::num($stats['gp'] ?? null),
            'gs' => self::num($stats['gs'] ?? null),
            'gms_active' => self::num($stats['gms_active'] ?? null),
            'off_snp' => self::num($stats['off_snp'] ?? null),
            'tm_off_snp' => self::num($stats['tm_off_snp'] ?? null),
            'tm_def_snp' => self::num($stats['tm_def_snp'] ?? null),
            'tm_st_snp' => self::num($stats['tm_st_snp'] ?? null),
            'rec' => self::num($stats['rec'] ?? null),
            'rec_tgt' => self::num($stats['rec_tgt'] ?? null),
            'rec_yd' => self::num($stats['rec_yd'] ?? null),
            'rec_td' => self::num($stats['rec_td'] ?? null),
            'rec_fd' => self::num($stats['rec_fd'] ?? null),
            'rec_air_yd' => self::num($stats['rec_air_yd'] ?? null),
            'rec_rz_tgt' => self::num($stats['rec_rz_tgt'] ?? null),
            'rec_lng' => self::num($stats['rec_lng'] ?? null),
            'rush_att' => self::num($stats['rush_att'] ?? null),
            'rush_yd' => self::num($stats['rush_yd'] ?? null),
            'rush_td' => self::num($stats['rush_td'] ?? null),
            'fum' => self::num($stats['fum'] ?? null),
            'fum_lost' => self::num($stats['fum_lost'] ?? null),
        ]);
    }

    protected function upsertProjections(Player $player, int $week, array $payload, string $season, string $sport, string $seasonType): void
    {
        // For weekly projections, the stats are nested under 'stats' key
        $stats = $payload['stats'] ?? $payload;

        PlayerProjections::updateOrCreate([
            'player_id' => (string) $player->player_id,
            'season' => $season,
            'week' => $week,
            'season_type' => $seasonType,
            'sport' => $sport,
            'company' => $payload['company'] ?? ($payload['category'] ?? null),
        ], [
            'date' => isset($payload['date']) ? Carbon::parse($payload['date'])->toDateString() : null,
            'team' => $payload['team'] ?? $player->team,
            'opponent' => $payload['opponent'] ?? null,
            'game_id' => $payload['game_id'] ?? null,
            'raw' => $payload,
            'updated_at_ms' => $payload['updated_at'] ?? null,
            'last_modified_ms' => $payload['last_modified'] ?? null,
            // Map ranking data to database columns (Sleeper API returns rankings, not actual stats)
            'pts_half_ppr' => self::num($stats['rank_half_ppr'] ?? null),
            'pts_ppr' => self::num($stats['rank_ppr'] ?? null),
            'pts_std' => self::num($stats['rank_std'] ?? null),
            'adp_dd_ppr' => self::num($stats['adp_dd_ppr'] ?? null),
            'pos_adp_dd_ppr' => self::num($stats['pos_adp_dd_ppr'] ?? null),
            'gp' => self::num($stats['gp'] ?? null),
            'gs' => self::num($stats['gs'] ?? null),
            'gms_active' => self::num($stats['gms_active'] ?? null),
            'off_snp' => self::num($stats['off_snp'] ?? null),
            'tm_off_snp' => self::num($stats['tm_off_snp'] ?? null),
            'tm_def_snp' => self::num($stats['tm_def_snp'] ?? null),
            'tm_st_snp' => self::num($stats['tm_st_snp'] ?? null),
            'rec' => self::num($stats['rec'] ?? null),
            'rec_tgt' => self::num($stats['rec_tgt'] ?? null),
            'rec_yd' => self::num($stats['rec_yd'] ?? null),
            'rec_td' => self::num($stats['rec_td'] ?? null),
            'rec_fd' => self::num($stats['rec_fd'] ?? null),
            'rec_air_yd' => self::num($stats['rec_air_yd'] ?? null),
            'rec_rz_tgt' => self::num($stats['rec_rz_tgt'] ?? null),
            'rec_lng' => self::num($stats['rec_lng'] ?? null),
            'rush_att' => self::num($stats['rush_att'] ?? null),
            'rush_yd' => self::num($stats['rush_yd'] ?? null),
            'rush_td' => self::num($stats['rush_td'] ?? null),
            'fum' => self::num($stats['fum'] ?? null),
            'fum_lost' => self::num($stats['fum_lost'] ?? null),
        ]);
    }

    protected static function num(null|int|float|string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }

    protected static function int(null|int|string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }



    protected function enforceRateLimitForChunk(int $processedInChunk, float $chunkStartedAt, int $maxPerMinute): void
    {
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
}
