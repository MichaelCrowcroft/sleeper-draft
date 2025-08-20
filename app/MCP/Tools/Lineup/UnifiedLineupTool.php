<?php

namespace App\MCP\Tools\Lineup;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class UnifiedLineupTool implements ToolInterface
{
    public function name(): string
    {
        return 'lineup_management';
    }

    public function description(): string
    {
        return 'Unified tool for lineup management: optimization, validation, and player comparison.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['mode'],
            'properties' => [
                'mode' => [
                    'type' => 'string',
                    'enum' => ['optimize', 'validate', 'compare'],
                    'description' => 'Type of lineup operation to perform',
                ],

                // Common parameters
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],

                // Optimize and validate mode parameters
                'league_id' => ['type' => 'string', 'description' => 'Required for optimize and validate modes'],
                'roster_id' => ['type' => 'integer', 'description' => 'Required for optimize and validate modes'],
                'strategy' => ['type' => 'string', 'enum' => ['median', 'ceiling', 'floor'], 'default' => 'median', 'description' => 'Optimize mode only'],

                // Compare mode parameters
                'player_a_id' => ['type' => 'string', 'description' => 'Compare mode only'],
                'player_b_id' => ['type' => 'string', 'description' => 'Compare mode only'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function annotations(): array
    {
        return [];
    }

    public function execute(array $arguments): mixed
    {
        $mode = $arguments['mode'];

        return match ($mode) {
            'optimize' => $this->optimizeLineup($arguments),
            'validate' => $this->validateLineup($arguments),
            'compare' => $this->comparePlayers($arguments),
            default => ['error' => 'Invalid mode specified']
        };
    }

    private function optimizeLineup(array $arguments): array
    {
        // Validate required parameters
        if (! isset($arguments['league_id']) || ! isset($arguments['roster_id'])) {
            throw new \InvalidArgumentException('Missing required parameters: league_id and roster_id');
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $sport = $arguments['sport'] ?? 'nfl';
        $state = $sdk->getState($sport);
        $season = (string) ($arguments['season'] ?? ($state['season'] ?? date('Y')));
        $week = (int) ($arguments['week'] ?? (int) ($state['week'] ?? 1));
        $leagueId = (string) $arguments['league_id'];
        $rosterId = (int) $arguments['roster_id'];
        $strategy = $arguments['strategy'] ?? 'median';

        $league = $sdk->getLeague($leagueId);
        $rosters = $sdk->getLeagueRosters($leagueId);
        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);

        $roster = collect($rosters)->firstWhere('sleeper_roster_id', (string) $rosterId) ?? [];
        $availablePlayers = array_map('strval', (array) ($roster['players'] ?? []));

        // Get roster positions
        $rosterPositions = array_values(array_filter((array) ($league['roster_positions'] ?? []), fn ($p) => ! in_array(strtoupper((string) $p), ['BN', 'IR', 'TAXI'], true)));

        // Group players by position
        $playersByPosition = [];
        foreach ($availablePlayers as $pid) {
            $meta = $catalog[$pid] ?? [];
            $pos = strtoupper((string) ($meta['position'] ?? ''));
            if (! isset($playersByPosition[$pos])) {
                $playersByPosition[$pos] = [];
            }
            $playersByPosition[$pos][] = [
                'player_id' => $pid,
                'name' => $meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')),
                'team' => $meta['team'] ?? null,
                'projected_points' => (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0)),
            ];
        }

        // Sort players by projected points
        foreach ($playersByPosition as $pos => $players) {
            usort($players, fn ($a, $b) => $b['projected_points'] <=> $a['projected_points']);
            $playersByPosition[$pos] = $players;
        }

        // Build optimal lineup
        $lineup = [];
        $usedPlayers = [];

        foreach ($rosterPositions as $slot) {
            $slot = strtoupper((string) $slot);
            $bestPlayer = null;

            if (in_array($slot, ['QB', 'RB', 'WR', 'TE'], true)) {
                // Direct position match
                if (! empty($playersByPosition[$slot])) {
                    foreach ($playersByPosition[$slot] as $player) {
                        if (! in_array($player['player_id'], $usedPlayers)) {
                            $bestPlayer = $player;
                            $usedPlayers[] = $player['player_id'];
                            break;
                        }
                    }
                }
            } elseif (in_array($slot, ['FLEX', 'WR_RB', 'WR_TE', 'RB_TE', 'REC_FLEX'], true)) {
                // Flexible position
                $eligiblePositions = match ($slot) {
                    'FLEX' => ['RB', 'WR', 'TE'],
                    'WR_RB' => ['WR', 'RB'],
                    'WR_TE' => ['WR', 'TE'],
                    'RB_TE' => ['RB', 'TE'],
                    'REC_FLEX' => ['WR', 'TE'],
                    default => [],
                };

                foreach ($eligiblePositions as $pos) {
                    if (! empty($playersByPosition[$pos])) {
                        foreach ($playersByPosition[$pos] as $player) {
                            if (! in_array($player['player_id'], $usedPlayers)) {
                                $bestPlayer = $player;
                                $usedPlayers[] = $player['player_id'];
                                break 2; // Break out of both loops
                            }
                        }
                    }
                }
            } elseif (in_array($slot, ['SUPER_FLEX', 'SUPERFLEX', 'SFLEX'], true)) {
                // Super flex - can use any position
                $allPositions = ['QB', 'RB', 'WR', 'TE'];
                foreach ($allPositions as $pos) {
                    if (! empty($playersByPosition[$pos])) {
                        foreach ($playersByPosition[$pos] as $player) {
                            if (! in_array($player['player_id'], $usedPlayers)) {
                                $bestPlayer = $player;
                                $usedPlayers[] = $player['player_id'];
                                break 2;
                            }
                        }
                    }
                }
            }

            $lineup[] = [
                'slot' => $slot,
                'player' => $bestPlayer,
            ];
        }

        // Calculate total projected points
        $totalProjected = 0;
        foreach ($lineup as $slot) {
            if ($slot['player']) {
                $totalProjected += $slot['player']['projected_points'];
            }
        }

        return [
            'mode' => 'optimize',
            'lineup' => $lineup,
            'total_projected_points' => $totalProjected,
            'strategy' => $strategy,
            'league_id' => $leagueId,
            'roster_id' => $rosterId,
            'season' => $season,
            'week' => $week,
        ];
    }

    private function validateLineup(array $arguments): array
    {
        // Validate required parameters
        if (! isset($arguments['league_id']) || ! isset($arguments['roster_id'])) {
            throw new \InvalidArgumentException('Missing required parameters: league_id and roster_id');
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $sport = $arguments['sport'] ?? 'nfl';
        $leagueId = (string) $arguments['league_id'];
        $rosterId = (int) $arguments['roster_id'];

        $league = $sdk->getLeague($leagueId);
        $rosters = $sdk->getLeagueRosters($leagueId);
        $catalog = $sdk->getPlayersCatalog($sport);

        $roster = collect($rosters)->firstWhere('sleeper_roster_id', (string) $rosterId) ?? [];
        $availablePlayers = array_map('strval', (array) ($roster['players'] ?? []));
        $rosterPositions = array_values(array_filter((array) ($league['roster_positions'] ?? []), fn ($p) => ! in_array(strtoupper((string) $p), ['BN', 'IR', 'TAXI'], true)));

        $validationResults = [
            'is_valid' => true,
            'issues' => [],
            'summary' => [],
        ];

        // Check if we have enough players
        if (count($availablePlayers) < count($rosterPositions)) {
            $validationResults['is_valid'] = false;
            $validationResults['issues'][] = 'Not enough players: have '.count($availablePlayers).', need '.count($rosterPositions);
        }

        // Check position requirements
        $positionCounts = [];
        foreach ($availablePlayers as $pid) {
            $meta = $catalog[$pid] ?? [];
            $pos = strtoupper((string) ($meta['position'] ?? ''));
            if (! isset($positionCounts[$pos])) {
                $positionCounts[$pos] = 0;
            }
            $positionCounts[$pos]++;
        }

        $validationResults['summary']['position_counts'] = $positionCounts;
        $validationResults['summary']['required_slots'] = count($rosterPositions);
        $validationResults['summary']['available_players'] = count($availablePlayers);

        return [
            'mode' => 'validate',
            'validation' => $validationResults,
            'league_id' => $leagueId,
            'roster_id' => $rosterId,
        ];
    }

    private function comparePlayers(array $arguments): array
    {
        // Validate required parameters
        if (! isset($arguments['player_a_id']) || ! isset($arguments['player_b_id'])) {
            throw new \InvalidArgumentException('Missing required parameters: player_a_id and player_b_id');
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $sport = $arguments['sport'] ?? 'nfl';
        $state = $sdk->getState($sport);
        $season = (string) ($arguments['season'] ?? ($state['season'] ?? date('Y')));
        $week = (int) ($arguments['week'] ?? (int) ($state['week'] ?? 1));
        $playerAId = (string) $arguments['player_a_id'];
        $playerBId = (string) $arguments['player_b_id'];

        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);

        $playerA = $catalog[$playerAId] ?? [];
        $playerB = $catalog[$playerBId] ?? [];

        $projA = (float) (($projections[$playerAId]['pts_half_ppr'] ?? $projections[$playerAId]['pts_ppr'] ?? $projections[$playerAId]['pts_std'] ?? 0));
        $projB = (float) (($projections[$playerBId]['pts_half_ppr'] ?? $projections[$playerBId]['pts_ppr'] ?? $projections[$playerBId]['pts_std'] ?? 0));

        $comparison = [
            'player_a' => [
                'player_id' => $playerAId,
                'name' => $playerA['full_name'] ?? trim(($playerA['first_name'] ?? '').' '.($playerA['last_name'] ?? '')),
                'position' => $playerA['position'] ?? null,
                'team' => $playerA['team'] ?? null,
                'projected_points' => $projA,
            ],
            'player_b' => [
                'player_id' => $playerBId,
                'name' => $playerB['full_name'] ?? trim(($playerB['first_name'] ?? '').' '.($playerB['last_name'] ?? '')),
                'position' => $playerB['position'] ?? null,
                'team' => $playerB['team'] ?? null,
                'projected_points' => $projB,
            ],
            'difference' => $projA - $projB,
            'recommendation' => $projA > $projB ? 'start_player_a' : ($projB > $projA ? 'start_player_b' : 'equal'),
        ];

        return [
            'mode' => 'compare',
            'comparison' => $comparison,
            'season' => $season,
            'week' => $week,
        ];
    }
}
