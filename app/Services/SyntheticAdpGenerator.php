<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyntheticAdpGenerator
{
    /**
     * Hardcoded reasonable ADP rankings for 2025 based on consensus
     * This serves as a fallback when all API sources fail
     */
    private const CONSENSUS_ADP_2025 = [
        // Quarterbacks
        'Josh Allen' => ['rank' => 1, 'position' => 'QB', 'team' => 'BUF'],
        'Patrick Mahomes' => ['rank' => 2, 'position' => 'QB', 'team' => 'KC'],
        'Lamar Jackson' => ['rank' => 3, 'position' => 'QB', 'team' => 'BAL'],
        'Jalen Hurts' => ['rank' => 4, 'position' => 'QB', 'team' => 'PHI'],
        'Dak Prescott' => ['rank' => 5, 'position' => 'QB', 'team' => 'DAL'],
        'Joe Burrow' => ['rank' => 6, 'position' => 'QB', 'team' => 'CIN'],
        'Kyler Murray' => ['rank' => 7, 'position' => 'QB', 'team' => 'ARI'],
        'Matthew Stafford' => ['rank' => 8, 'position' => 'QB', 'team' => 'LAR'],
        'Brock Purdy' => ['rank' => 9, 'position' => 'QB', 'team' => 'SF'],
        'Jordan Love' => ['rank' => 10, 'position' => 'QB', 'team' => 'GB'],

        // Running Backs
        'Christian McCaffrey' => ['rank' => 11, 'position' => 'RB', 'team' => 'SF'],
        'Austin Ekeler' => ['rank' => 12, 'position' => 'RB', 'team' => 'WAS'],
        'Derrick Henry' => ['rank' => 13, 'position' => 'RB', 'team' => 'BAL'],
        'Saquon Barkley' => ['rank' => 14, 'position' => 'RB', 'team' => 'PHI'],
        'Nick Chubb' => ['rank' => 15, 'position' => 'RB', 'team' => 'CLE'],
        'Travis Etienne' => ['rank' => 16, 'position' => 'RB', 'team' => 'JAX'],
        'Alvin Kamara' => ['rank' => 17, 'position' => 'RB', 'team' => 'NO'],
        'Josh Jacobs' => ['rank' => 18, 'position' => 'RB', 'team' => 'GB'],
        'Rhamondre Stevenson' => ['rank' => 19, 'position' => 'RB', 'team' => 'NE'],
        'Joe Mixon' => ['rank' => 20, 'position' => 'RB', 'team' => 'HOU'],

        // Wide Receivers
        'Tyreek Hill' => ['rank' => 21, 'position' => 'WR', 'team' => 'MIA'],
        'Cooper Kupp' => ['rank' => 22, 'position' => 'WR', 'team' => 'LAR'],
        'Stefon Diggs' => ['rank' => 23, 'position' => 'WR', 'team' => 'HOU'],
        'Davante Adams' => ['rank' => 24, 'position' => 'WR', 'team' => 'LV'],
        'Ja\'Marr Chase' => ['rank' => 25, 'position' => 'WR', 'team' => 'CIN'],
        'Mike Evans' => ['rank' => 26, 'position' => 'WR', 'team' => 'TB'],
        'Amon-Ra St. Brown' => ['rank' => 27, 'position' => 'WR', 'team' => 'DET'],
        'CeeDee Lamb' => ['rank' => 28, 'position' => 'WR', 'team' => 'DAL'],
        'Garrett Wilson' => ['rank' => 29, 'position' => 'WR', 'team' => 'NYJ'],
        'Chris Olave' => ['rank' => 30, 'position' => 'WR', 'team' => 'NO'],
        'DeVonta Smith' => ['rank' => 31, 'position' => 'WR', 'team' => 'PHI'],
        'DK Metcalf' => ['rank' => 32, 'position' => 'WR', 'team' => 'SEA'],
        'Tee Higgins' => ['rank' => 33, 'position' => 'WR', 'team' => 'CIN'],
        'Calvin Ridley' => ['rank' => 34, 'position' => 'WR', 'team' => 'TEN'],
        'Drake London' => ['rank' => 35, 'position' => 'WR', 'team' => 'ATL'],
        'George Pickens' => ['rank' => 36, 'position' => 'WR', 'team' => 'PIT'],
        'DeAndre Hopkins' => ['rank' => 37, 'position' => 'WR', 'team' => 'TEN'],
        'Keenan Allen' => ['rank' => 38, 'position' => 'WR', 'team' => 'CHI'],
        'Brandon Aiyuk' => ['rank' => 39, 'position' => 'WR', 'team' => 'SF'],
        'Justin Jefferson' => ['rank' => 40, 'position' => 'WR', 'team' => 'MIN'],

        // Tight Ends
        'Travis Kelce' => ['rank' => 41, 'position' => 'TE', 'team' => 'KC'],
        'Sam LaPorta' => ['rank' => 42, 'position' => 'TE', 'team' => 'DET'],
        'Kyle Pitts' => ['rank' => 43, 'position' => 'TE', 'team' => 'ATL'],
        'George Kittle' => ['rank' => 44, 'position' => 'TE', 'team' => 'SF'],
        'Mark Andrews' => ['rank' => 45, 'position' => 'TE', 'team' => 'BAL'],
        'T.J. Hockenson' => ['rank' => 46, 'position' => 'TE', 'team' => 'MIN'],
        'Dallas Goedert' => ['rank' => 47, 'position' => 'TE', 'team' => 'PHI'],
        'Pat Freiermuth' => ['rank' => 48, 'position' => 'TE', 'team' => 'PIT'],
        'Evan Engram' => ['rank' => 49, 'position' => 'TE', 'team' => 'JAX'],
        'Cole Kmet' => ['rank' => 50, 'position' => 'TE', 'team' => 'CHI'],

        // More Quarterbacks
        'Justin Herbert' => ['rank' => 51, 'position' => 'QB', 'team' => 'LAC'],
        'Geno Smith' => ['rank' => 52, 'position' => 'QB', 'team' => 'SEA'],
        'Kirk Cousins' => ['rank' => 53, 'position' => 'QB', 'team' => 'MIN'],
        'Derek Carr' => ['rank' => 54, 'position' => 'QB', 'team' => 'NO'],
        'Aaron Rodgers' => ['rank' => 55, 'position' => 'QB', 'team' => 'NYJ'],
        'Tua Tagovailoa' => ['rank' => 56, 'position' => 'QB', 'team' => 'MIA'],
        'Deshaun Watson' => ['rank' => 57, 'position' => 'QB', 'team' => 'CLE'],
        'Ryan Tannehill' => ['rank' => 58, 'position' => 'QB', 'team' => 'TEN'],
        'Trevor Lawrence' => ['rank' => 59, 'position' => 'QB', 'team' => 'JAX'],
        'Daniel Jones' => ['rank' => 60, 'position' => 'QB', 'team' => 'NYG'],
    ];

    /**
     * Generate synthetic ADP data based on hardcoded consensus rankings
     */
    public function generateConsensusAdp(string $season, string $sport = 'nfl'): array
    {
        $cacheKey = "synthetic_adp_consensus:$season";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($season, $sport) {
            $syntheticData = [];
            $rank = 1;

            // Get the actual player catalog to find correct player IDs
            $sleeperSdk = app(SleeperSdk::class);
            $catalog = $sleeperSdk->getPlayersCatalog($sport);

            // Create a name-to-ID mapping from the actual catalog
            $nameToIdMap = [];
            foreach ($catalog as $playerId => $player) {
                $fullName = trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''));
                if (!empty($fullName)) {
                    $nameToIdMap[strtolower($fullName)] = $playerId;
                }
            }

            foreach (self::CONSENSUS_ADP_2025 as $playerName => $data) {
                // Find the player ID from the actual catalog
                $playerId = $nameToIdMap[strtolower($playerName)] ?? null;

                if ($playerId) {
                    $syntheticData[] = [
                        'player_id' => (string) $playerId,
                        'adp' => (float) $data['rank'],
                        'source' => 'consensus_synthetic',
                    ];
                } else {
                    Log::warning("Could not find player ID for $playerName in catalog");
                }

                $rank++;
                // No limit - include all consensus players
            }

            Log::info("Generated consensus synthetic ADP", [
                'count' => count($syntheticData),
                'season' => $season,
                'total_consensus_players' => count(self::CONSENSUS_ADP_2025)
            ]);

            return $syntheticData;
        });
    }

    /**
     * Generate synthetic ADP based on position and basic heuristics
     */
    public function generateHeuristicAdp(string $season, SleeperSdk $sleeperSdk, string $sport = 'nfl'): array
    {
        $cacheKey = "synthetic_adp_heuristic:$season";

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($season, $sleeperSdk, $sport) {
            $catalog = $sleeperSdk->getPlayersCatalog($sport);

            // Group players by position and team quality
            $playersByPosition = [];
            foreach ($catalog as $playerId => $meta) {
                $position = strtoupper($meta['position'] ?? '');
                $team = strtoupper($meta['team'] ?? '');

                if (in_array($position, ['QB', 'RB', 'WR', 'TE']) && !empty($team)) {
                    $playersByPosition[$position][] = [
                        'id' => $playerId,
                        'meta' => $meta,
                        'team_score' => $this->getTeamScore($team),
                    ];
                }
            }

            $syntheticAdp = [];
            $overallRank = 1;

            // Sort positions by typical draft priority
            $positionOrder = ['QB', 'RB', 'WR', 'TE'];

            foreach ($positionOrder as $position) {
                if (!isset($playersByPosition[$position])) {
                    continue;
                }

                $players = $playersByPosition[$position];

                // Sort by team quality (rough heuristic)
                usort($players, function ($a, $b) {
                    return $b['team_score'] <=> $a['team_score'];
                });

                // Assign ADP based on position and team quality
                $positionRank = 1;
                foreach ($players as $player) {
                    // Position multiplier gives QBs higher rankings
                    $positionMultiplier = match($position) {
                        'QB' => 1.0,
                        'RB' => 1.2,
                        'WR' => 1.3,
                        'TE' => 1.5,
                        default => 1.0
                    };

                    $adjustedRank = $overallRank + ($positionRank * $positionMultiplier);

                    $syntheticAdp[] = [
                        'player_id' => (string) $player['id'],
                        'adp' => (float) $adjustedRank,
                        'source' => 'heuristic_synthetic',
                    ];

                    $positionRank++;
                    $overallRank++;

                    // Limit to reasonable number per position
                    if ($positionRank > 50) {
                        break;
                    }
                }
            }

            // Sort final result by ADP
            usort($syntheticAdp, fn($a, $b) => $a['adp'] <=> $b['adp']);

            Log::info("Generated heuristic synthetic ADP", [
                'count' => count($syntheticAdp),
                'season' => $season
            ]);

            return $syntheticAdp;
        });
    }

    /**
     * Simple team scoring heuristic
     */
    private function getTeamScore(string $team): int
    {
        $teamScores = [
            'KC' => 95, 'BUF' => 90, 'SF' => 88, 'DAL' => 85, 'PHI' => 85,
            'BAL' => 83, 'CIN' => 82, 'MIA' => 80, 'LAR' => 78, 'SEA' => 78,
            'TB' => 76, 'DET' => 75, 'CLE' => 74, 'NO' => 74, 'GB' => 73,
            'PIT' => 72, 'ATL' => 72, 'LV' => 70, 'ARI' => 70, 'NE' => 68,
            'JAX' => 68, 'NYJ' => 65, 'TEN' => 65, 'WAS' => 65, 'IND' => 63,
            'CAR' => 62, 'MIN' => 62, 'LAC' => 60, 'DEN' => 60, 'NYG' => 58,
            'CHI' => 55, 'HOU' => 55,
        ];

        return $teamScores[$team] ?? 50; // Default score for unknown teams
    }

    /**
     * Map player names to Sleeper player IDs
     * In a real implementation, this would use a proper mapping table
     */
    private function mapPlayerNameToId(string $playerName): ?string
    {
        // This is a simplified mapping - in production you'd have a comprehensive lookup table
        $nameToIdMap = [
            // Quarterbacks
            'Josh Allen' => '4034',
            'Patrick Mahomes' => '4039',
            'Lamar Jackson' => '4886',
            'Jalen Hurts' => '4887',
            'Dak Prescott' => '4034', // Using Josh Allen ID as placeholder
            'Joe Burrow' => '4888',
            'Kyler Murray' => '4889',
            'Matthew Stafford' => '4890',
            'Brock Purdy' => '4891',
            'Jordan Love' => '4892',
            'Justin Herbert' => '6794',
            'Geno Smith' => '4034', // placeholder
            'Kirk Cousins' => '4034', // placeholder
            'Derek Carr' => '4034', // placeholder
            'Aaron Rodgers' => '4034', // placeholder
            'Tua Tagovailoa' => '4034', // placeholder
            'Deshaun Watson' => '4034', // placeholder
            'Ryan Tannehill' => '4034', // placeholder
            'Trevor Lawrence' => '4034', // placeholder
            'Daniel Jones' => '4034', // placeholder

            // Running Backs
            'Christian McCaffrey' => '4046',
            'Austin Ekeler' => '4037',
            'Derrick Henry' => '4040',
            'Saquon Barkley' => '4041',
            'Nick Chubb' => '4038',
            'Travis Etienne' => '6798',
            'Alvin Kamara' => '4035',
            'Josh Jacobs' => '4883',
            'Rhamondre Stevenson' => '6800',
            'Joe Mixon' => '4034', // placeholder

            // Wide Receivers
            'Tyreek Hill' => '4881',
            'Cooper Kupp' => '4029',
            'Stefon Diggs' => '4033',
            'Davante Adams' => '4018',
            'Ja\'Marr Chase' => '4880',
            'Mike Evans' => '4032',
            'Amon-Ra St. Brown' => '6803',
            'CeeDee Lamb' => '6797',
            'Garrett Wilson' => '6801',
            'Chris Olave' => '6802',
            'DeVonta Smith' => '6799',
            'DK Metcalf' => '4882',
            'Tee Higgins' => '4884',
            'Calvin Ridley' => '4036',
            'Drake London' => '6804',
            'George Pickens' => '6805',
            'DeAndre Hopkins' => '4019',
            'Keenan Allen' => '4020',
            'Brandon Aiyuk' => '6796',
            'Justin Jefferson' => '6790',

            // Tight Ends
            'Travis Kelce' => '4031',
            'Sam LaPorta' => '7564',
            'Kyle Pitts' => '6795',
            'George Kittle' => '4034', // placeholder
            'Mark Andrews' => '4034', // placeholder
            'T.J. Hockenson' => '4034', // placeholder
            'Dallas Goedert' => '4034', // placeholder
            'Pat Freiermuth' => '4034', // placeholder
            'Evan Engram' => '4034', // placeholder
            'Cole Kmet' => '4034', // placeholder
        ];

        return $nameToIdMap[$playerName] ?? null;
    }
}
