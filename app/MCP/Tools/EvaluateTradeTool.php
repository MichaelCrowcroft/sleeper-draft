<?php

namespace App\MCP\Tools;

use App\Models\Player;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class EvaluateTradeTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'evaluate-trade';
    }

    public function description(): string
    {
        return 'Evaluate a fantasy football trade by analyzing player stats, projections, and providing aggregate comparison between receiving and sending sides.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'receiving' => [
                    'type' => 'array',
                    'description' => 'List of players being received in the trade',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'player_id' => [
                                'type' => 'string',
                                'description' => 'Sleeper player ID',
                            ],
                            'search' => [
                                'type' => 'string',
                                'description' => 'Search term to find player by name',
                            ],
                        ],
                        'oneOf' => [
                            ['required' => ['player_id']],
                            ['required' => ['search']],
                        ],
                    ],
                    'minItems' => 1,
                ],
                'sending' => [
                    'type' => 'array',
                    'description' => 'List of players being sent in the trade',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'player_id' => [
                                'type' => 'string',
                                'description' => 'Sleeper player ID',
                            ],
                            'search' => [
                                'type' => 'string',
                                'description' => 'Search term to find player by name',
                            ],
                        ],
                        'oneOf' => [
                            ['required' => ['player_id']],
                            ['required' => ['search']],
                        ],
                    ],
                    'minItems' => 1,
                ],
            ],
            'required' => ['receiving', 'sending'],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Evaluate Fantasy Trade',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
            'category' => 'fantasy-sports',
            'data_source' => 'database',
            'cache_recommended' => true,
            'notes' => 'Analyzes trade value by comparing 2024 performance, 2025 projections, and position-specific metrics for both sides of the trade.',
        ];
    }

    public function execute(array $arguments): mixed
    {
        $receivingPlayers = $this->findPlayers($arguments['receiving']);
        $sendingPlayers = $this->findPlayers($arguments['sending']);

        // Check for any missing players
        $missingReceiving = $this->getMissingPlayers($arguments['receiving'], $receivingPlayers);
        $missingSending = $this->getMissingPlayers($arguments['sending'], $sendingPlayers);

        if (!empty($missingReceiving) || !empty($missingSending)) {
            return [
                'success' => false,
                'error' => 'Some players not found',
                'missing_receiving' => $missingReceiving,
                'missing_sending' => $missingSending,
                'message' => 'Unable to find all specified players for trade evaluation',
            ];
        }

        // Load detailed data for all players
        $receivingData = $this->collectPlayersData($receivingPlayers);
        $sendingData = $this->collectPlayersData($sendingPlayers);

        // Calculate aggregates
        $receivingAggregates = $this->calculateAggregates($receivingData);
        $sendingAggregates = $this->calculateAggregates($sendingData);

        // Trade analysis
        $tradeAnalysis = $this->analyzeTrade($receivingAggregates, $sendingAggregates);

        return [
            'success' => true,
            'data' => [
                'receiving' => [
                    'players' => $receivingData,
                    'aggregates' => $receivingAggregates,
                ],
                'sending' => [
                    'players' => $sendingData,
                    'aggregates' => $sendingAggregates,
                ],
                'analysis' => $tradeAnalysis,
            ],
            'message' => 'Trade evaluation completed successfully',
            'metadata' => [
                'receiving_count' => count($receivingData),
                'sending_count' => count($sendingData),
                'evaluated_at' => now()->toISOString(),
                'data_sources' => [
                    'player_table' => true,
                    'stats_2024' => true,
                    'projections_2025' => true,
                    'season_summaries' => true,
                ],
            ],
        ];
    }

    private function findPlayers(array $playerRequests): array
    {
        $players = [];

        foreach ($playerRequests as $request) {
            $player = null;

            if (isset($request['player_id'])) {
                $player = Player::where('player_id', $request['player_id'])->first();
            } elseif (isset($request['search'])) {
                $searchTerm = trim($request['search']);
                $player = Player::search($searchTerm)
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->first();
            }

            if ($player) {
                $players[] = $player;
            }
        }

        return $players;
    }

    private function getMissingPlayers(array $requests, array $foundPlayers): array
    {
        $missing = [];
        $foundCount = 0;

        foreach ($requests as $request) {
            $found = false;
            $identifier = isset($request['player_id']) ? $request['player_id'] : $request['search'];

            // Check if we found a corresponding player
            if ($foundCount < count($foundPlayers)) {
                $found = true;
                $foundCount++;
            }

            if (!$found) {
                $missing[] = [
                    'identifier' => $identifier,
                    'type' => isset($request['player_id']) ? 'player_id' : 'search',
                ];
            }
        }

        return $missing;
    }

    private function collectPlayersData(array $players): array
    {
        $data = [];

        foreach ($players as $player) {
            // Load necessary relationships
            $player->load([
                'stats2024',
                'seasonSummaries' => fn ($q) => $q->where('season', 2024),
                'projections2025',
            ]);

            $data[] = $this->collectPlayerData($player);
        }

        return $data;
    }

    private function collectPlayerData(Player $player): array
    {
        // Basic player info
        $basicInfo = [
            'player_id' => $player->player_id,
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'full_name' => $player->full_name,
            'position' => $this->getPosition($player),
            'team' => $player->team,
            'age' => $player->age,
            'injury_status' => $player->injury_status,
            'adp' => $player->adp,
        ];

        // 2024 Season data
        $stats2024 = $player->getSeason2024Totals();
        $summary2024 = $player->getSeason2024Summary();

        // 2025 Projections
        $projections2025 = $player->getSeason2025ProjectionSummary();

        return [
            'basic_info' => $basicInfo,
            'season_2024' => [
                'stats' => $stats2024,
                'summary' => $summary2024,
                'total_points' => $summary2024['total_points'] ?? 0,
                'average_points_per_game' => $summary2024['average_points_per_game'] ?? 0,
                'games_active' => $summary2024['games_active'] ?? 0,
            ],
            'season_2025' => [
                'projections' => $projections2025,
                'total_projected_points' => $projections2025['total_points'] ?? 0,
                'average_projected_points_per_game' => $projections2025['average_points_per_game'] ?? 0,
                'projected_games' => $projections2025['games'] ?? 0,
            ],
        ];
    }

    private function getPosition(Player $player): string
    {
        // Use fantasy_positions if available, fallback to position
        $fantasyPositions = $player->fantasy_positions;
        if (is_array($fantasyPositions) && ! empty($fantasyPositions)) {
            return $fantasyPositions[0];
        }

        return $player->position ?? 'UNKNOWN';
    }

    private function calculateAggregates(array $playersData): array
    {
        if (empty($playersData)) {
            return [
                'total_players' => 0,
                'position_breakdown' => [],
                'season_2024' => [
                    'total_points' => 0,
                    'average_points_per_game' => 0,
                    'total_games' => 0,
                    'team_average' => 0,
                ],
                'season_2025' => [
                    'total_projected_points' => 0,
                    'average_projected_points_per_game' => 0,
                    'total_projected_games' => 0,
                    'team_average' => 0,
                ],
            ];
        }

        $positionCounts = [];
        $total2024Points = 0;
        $total2024Games = 0;
        $total2025Points = 0;
        $total2025Games = 0;

        foreach ($playersData as $player) {
            $position = $player['basic_info']['position'];
            $positionCounts[$position] = ($positionCounts[$position] ?? 0) + 1;

            $total2024Points += (float) ($player['season_2024']['total_points'] ?? 0);
            $total2024Games += (int) ($player['season_2024']['games_active'] ?? 0);
            $total2025Points += (float) ($player['season_2025']['total_projected_points'] ?? 0);
            $total2025Games += (int) ($player['season_2025']['projected_games'] ?? 0);
        }

        $playerCount = count($playersData);
        $avg2024PerGame = $total2024Games > 0 ? $total2024Points / $total2024Games : 0;
        $avg2025PerGame = $total2025Games > 0 ? $total2025Points / $total2025Games : 0;

        return [
            'total_players' => $playerCount,
            'position_breakdown' => $positionCounts,
            'season_2024' => [
                'total_points' => $total2024Points,
                'average_points_per_game' => $avg2024PerGame,
                'total_games' => $total2024Games,
                'team_average' => $playerCount > 0 ? $total2024Points / $playerCount : 0,
            ],
            'season_2025' => [
                'total_projected_points' => $total2025Points,
                'average_projected_points_per_game' => $avg2025PerGame,
                'total_projected_games' => $total2025Games,
                'team_average' => $playerCount > 0 ? $total2025Points / $playerCount : 0,
            ],
        ];
    }

    private function analyzeTrade(array $receivingAggregates, array $sendingAggregates): array
    {
        $receiving2024 = $receivingAggregates['season_2024'];
        $sending2024 = $sendingAggregates['season_2024'];
        $receiving2025 = $receivingAggregates['season_2025'];
        $sending2025 = $sendingAggregates['season_2025'];

        // Value differential
        $points2024Diff = $receiving2024['total_points'] - $sending2024['total_points'];
        $avg2024Diff = $receiving2024['average_points_per_game'] - $sending2024['average_points_per_game'];
        $points2025Diff = $receiving2025['total_projected_points'] - $sending2025['total_projected_points'];
        $avg2025Diff = $receiving2025['average_projected_points_per_game'] - $sending2025['average_projected_points_per_game'];

        // Trade recommendation logic
        $recommendation = $this->getTradeRecommendation($points2024Diff, $points2025Diff);

        return [
            'value_differential' => [
                'season_2024' => [
                    'points_difference' => $points2024Diff,
                    'avg_points_per_game_difference' => $avg2024Diff,
                    'receiving_advantage' => $points2024Diff > 0,
                ],
                'season_2025' => [
                    'points_difference' => $points2025Diff,
                    'avg_points_per_game_difference' => $avg2025Diff,
                    'receiving_advantage' => $points2025Diff > 0,
                ],
            ],
            'recommendation' => $recommendation,
            'key_insights' => $this->generateKeyInsights($receivingAggregates, $sendingAggregates),
        ];
    }

    private function getTradeRecommendation(float $points2024Diff, float $points2025Diff): array
    {
        $threshold = 20; // Points threshold for strong recommendation

        if ($points2025Diff > $threshold) {
            return [
                'action' => 'ACCEPT',
                'confidence' => 'high',
                'reason' => 'Strong projected advantage in 2025 season',
            ];
        } elseif ($points2025Diff > 0) {
            return [
                'action' => 'ACCEPT',
                'confidence' => 'medium',
                'reason' => 'Projected advantage in 2025 season',
            ];
        } elseif ($points2025Diff > -$threshold) {
            return [
                'action' => 'NEGOTIATE',
                'confidence' => 'medium',
                'reason' => 'Close projected values, consider negotiation',
            ];
        } else {
            return [
                'action' => 'DECLINE',
                'confidence' => 'high',
                'reason' => 'Projected disadvantage in 2025 season',
            ];
        }
    }

    private function generateKeyInsights(array $receivingAggregates, array $sendingAggregates): array
    {
        $insights = [];

        $receiving2025 = $receivingAggregates['season_2025'];
        $sending2025 = $sendingAggregates['season_2025'];

        // Compare position distributions
        $receivingPositions = $receivingAggregates['position_breakdown'];
        $sendingPositions = $sendingAggregates['position_breakdown'];

        if ($receiving2025['total_projected_points'] > $sending2025['total_projected_points']) {
            $insights[] = 'Receiving side has higher projected total points';
        }

        if ($receiving2025['average_projected_points_per_game'] > $sending2025['average_projected_points_per_game']) {
            $insights[] = 'Receiving side has higher average projected PPG';
        }

        // Position diversity insight
        $receivingPositionCount = count($receivingPositions);
        $sendingPositionCount = count($sendingPositions);

        if ($receivingPositionCount > $sendingPositionCount) {
            $insights[] = 'Receiving side offers better position diversity';
        }

        // QB analysis
        if (isset($receivingPositions['QB']) && !isset($sendingPositions['QB'])) {
            $insights[] = 'Receiving a QB while not sending one';
        } elseif (!isset($receivingPositions['QB']) && isset($sendingPositions['QB'])) {
            $insights[] = 'Sending a QB without receiving one';
        }

        return $insights;
    }
}
