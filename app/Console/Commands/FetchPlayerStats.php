<?php

namespace App\Console\Commands;

use App\Models\PlayerStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class FetchPlayerStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sleeper:player:stats
                           {player_id : The Sleeper player ID (e.g., 6794)}
                           {--season=2025 : The season year (default: 2025)}
                           {--grouping=week : The grouping type (week, season)}
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
    public function handle()
    {
        $playerId = $this->argument('player_id');
        $season = $this->option('season');
        $grouping = $this->option('grouping');

        $response = Http::get("https://api.sleeper.com/stats/nfl/player/{$playerId}", [
            'season_type' => 'regular',
            'season' => $season,
            'grouping' => $grouping,
        ]);

        $data = $response->json();

        if (!$data) {
            $this->error('No data received from API');
            return;
        }

        // Handle different response structures based on grouping
        $flattenedData = [];

        if ($grouping === 'season') {
            // Season grouping returns a single object with stats
            if (isset($data['stats'])) {
                $flattenedData[null] = array_merge(
                    array_diff_key($data, ['stats' => '']),
                    $data['stats']
                );
            } else {
                $flattenedData[null] = $data;
            }
        } else {
            // Week grouping returns an array of week objects
            if (is_array($data)) {
                foreach ($data as $week => $weekData) {
                    if (is_array($weekData)) {
                        $flattenedWeek = $weekData;

                        // If stats key exists, flatten it
                        if (isset($weekData['stats'])) {
                            $stats = $weekData['stats'];
                            unset($flattenedWeek['stats']);
                            $flattenedWeek = array_merge($flattenedWeek, $stats);
                        }

                        $flattenedData[$week] = $flattenedWeek;
                    }
                }
            }
        }

        // Upsert the data to database
        $this->upsertPlayerStats($flattenedData, $season, $grouping);

        $this->info("Successfully upserted player stats for player {$playerId}");
    }

    /**
     * Upsert player stats data to the database.
     */
    private function upsertPlayerStats(array $data, string $season, string $grouping): void
    {
        $statsToUpsert = [];

        foreach ($data as $week => $weekData) {
            // Skip null entries (weeks with no games)
            if ($weekData === null) {
                continue;
            }

            $statsRecord = [
                'player_id' => $weekData['player_id'] ?? null,
                'sport' => $weekData['sport'] ?? 'nfl',
                'season' => (int) $season,
                'week' => $week === null ? null : (int) $week,
                'season_type' => $weekData['season_type'] ?? 'regular',
                'date' => isset($weekData['date']) ? $weekData['date'] : null,
                'team' => $weekData['team'] ?? null,
                'opponent' => $weekData['opponent'] ?? null,
                'game_id' => $weekData['game_id'] ?? null,
                'company' => $weekData['company'] ?? 'sportradar',
                'updated_at_ms' => isset($weekData['updated_at']) ? (int) $weekData['updated_at'] : null,
                'last_modified_ms' => isset($weekData['last_modified']) ? (int) $weekData['last_modified'] : null,

                // Fantasy points
                'pts_half_ppr' => $weekData['pts_half_ppr'] ?? null,
                'pts_ppr' => $weekData['pts_ppr'] ?? null,
                'pts_std' => $weekData['pts_std'] ?? null,

                // Position rankings
                'pos_rank_half_ppr' => $weekData['pos_rank_half_ppr'] ?? null,
                'pos_rank_ppr' => $weekData['pos_rank_ppr'] ?? null,
                'pos_rank_std' => $weekData['pos_rank_std'] ?? null,

                // Game participation
                'gp' => $weekData['gp'] ?? null,
                'gs' => $weekData['gs'] ?? null,
                'gms_active' => $weekData['gms_active'] ?? null,
                'off_snp' => $weekData['off_snp'] ?? null,
                'tm_off_snp' => $weekData['tm_off_snp'] ?? null,
                'tm_def_snp' => $weekData['tm_def_snp'] ?? null,
                'tm_st_snp' => $weekData['tm_st_snp'] ?? null,
                'st_snp' => $weekData['st_snp'] ?? null,

                // Receiving stats
                'rec' => $weekData['rec'] ?? null,
                'rec_tgt' => $weekData['rec_tgt'] ?? null,
                'rec_yd' => $weekData['rec_yd'] ?? null,
                'rec_td' => $weekData['rec_td'] ?? null,
                'rec_fd' => $weekData['rec_fd'] ?? null,
                'rec_air_yd' => $weekData['rec_air_yd'] ?? null,
                'rec_rz_tgt' => $weekData['rec_rz_tgt'] ?? null,
                'rec_lng' => $weekData['rec_lng'] ?? null,
                'rec_ypr' => $weekData['rec_ypr'] ?? null,
                'rec_ypt' => $weekData['rec_ypt'] ?? null,
                'rec_yar' => $weekData['rec_yar'] ?? null,
                'rec_drop' => $weekData['rec_drop'] ?? null,

                // Receiving distance breakdowns
                'rec_0_4' => $weekData['rec_0_4'] ?? null,
                'rec_5_9' => $weekData['rec_5_9'] ?? null,
                'rec_10_19' => $weekData['rec_10_19'] ?? null,
                'rec_20_29' => $weekData['rec_20_29'] ?? null,
                'rec_30_39' => $weekData['rec_30_39'] ?? null,
                'rec_40p' => $weekData['rec_40p'] ?? null,

                // Receiving TD details
                'rec_td_lng' => $weekData['rec_td_lng'] ?? null,
                'rec_td_40p' => $weekData['rec_td_40p'] ?? null,
                'rec_td_50p' => $weekData['rec_td_50p'] ?? null,

                // Rushing stats
                'rush_att' => $weekData['rush_att'] ?? null,
                'rush_yd' => $weekData['rush_yd'] ?? null,
                'rush_td' => $weekData['rush_td'] ?? null,
                'rush_fd' => $weekData['rush_fd'] ?? null,
                'rush_lng' => $weekData['rush_lng'] ?? null,
                'rush_ypa' => $weekData['rush_ypa'] ?? null,

                // Combined rushing/receiving yards
                'rush_rec_yd' => $weekData['rush_rec_yd'] ?? null,

                // Passing stats
                'pass_cmp' => $weekData['pass_cmp'] ?? null,
                'pass_att' => $weekData['pass_att'] ?? null,
                'pass_yd' => $weekData['pass_yd'] ?? null,
                'pass_td' => $weekData['pass_td'] ?? null,
                'pass_int' => $weekData['pass_int'] ?? null,
                'pass_sacked' => $weekData['pass_sacked'] ?? null,
                'pass_sacked_yd' => $weekData['pass_sacked_yd'] ?? null,
                'pass_rtg' => $weekData['pass_rtg'] ?? null,
                'cmp_pct' => $weekData['cmp_pct'] ?? null,
                'pass_ypa' => $weekData['pass_ypa'] ?? null,
                'pass_ypc' => $weekData['pass_ypc'] ?? null,
                'pass_lng' => $weekData['pass_lng'] ?? null,
                'pass_fd' => $weekData['pass_fd'] ?? null,
                'pass_air_yd' => $weekData['pass_air_yd'] ?? null,
                'pass_rush_yd' => $weekData['pass_rush_yd'] ?? null,

                // Fumbles
                'fum' => $weekData['fum'] ?? null,
                'fum_lost' => $weekData['fum_lost'] ?? null,

                // Store raw data for future reference
                'raw' => json_encode($weekData),
            ];

            $statsToUpsert[] = $statsRecord;
        }

        if (!empty($statsToUpsert)) {
            PlayerStats::upsert(
                $statsToUpsert,
                ['player_id', 'season', 'week', 'season_type', 'company', 'sport'], // Unique columns
                array_keys($statsToUpsert[0]) // All columns to update
            );

            $this->info("Upserted " . count($statsToUpsert) . " player stats records");
        }
    }
}
