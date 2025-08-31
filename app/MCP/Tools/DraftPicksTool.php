<?php

namespace App\MCP\Tools;

use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class DraftPicksTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'draft-picks';
    }

    public function description(): string
    {
        return 'Provides intelligent draft pick suggestions by analyzing draft state, user roster composition, scoring settings, and player ADP values. Considers snake draft mechanics and positional needs.

ADP (Average Draft Position) Data Types:
- adp: Regular numeric value where lower numbers indicate players expected to be drafted earlier
- adp_formatted: Human-readable string format showing round and pick (e.g., "1.05", "2.12", "5.08")
- adp_high/adp_low: Range of ADP values from different sources
- adp_stdev: Standard deviation showing ADP consistency across sources

ADP_formatted Interpretation:
- Format: "round.pick" (e.g., "1.05", "2.12", "5.08")
- Whole number = draft round (1 = Round 1, 2 = Round 2, etc.)
- Decimal portion = pick within that round (.05 = Pick 5, .12 = Pick 12, etc.)
- Examples: "1.05" = Round 1, Pick 5; "2.12" = Round 2, Pick 12; "5.08" = Round 5, Pick 8
- Lower ADP_formatted values indicate players expected to be drafted earlier
- ADP helps predict when players will be available in your draft';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'user_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper user ID',
                ],
                'draft_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper draft ID',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of suggestions to return (default: 10)',
                    'minimum' => 1,
                    'maximum' => 50,
                ],
            ],
            'required' => ['user_id', 'draft_id'],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Draft Picks Recommendations',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => true, // Makes API calls to Sleeper

            // Custom annotations
            'category' => 'fantasy-sports',
            'data_source' => 'external_api',
            'cache_recommended' => false,

            // ADP interpretation guide
            'adp_interpretation' => [
                'data_types' => [
                    'adp' => 'Regular numeric value (lower = drafted earlier)',
                    'adp_formatted' => 'String format "round.pick" (e.g., "1.05")',
                    'adp_high' => 'Highest ADP value from sources',
                    'adp_low' => 'Lowest ADP value from sources',
                    'adp_stdev' => 'Standard deviation of ADP values',
                ],
                'adp_formatted_format' => 'round.pick (e.g., "1.05", "2.12", "5.08")',
                'adp_formatted_examples' => [
                    '1.05' => 'Round 1, Pick 5',
                    '2.12' => 'Round 2, Pick 12',
                    '5.08' => 'Round 5, Pick 8',
                    '12.03' => 'Round 12, Pick 3',
                ],
                'meaning' => 'Lower ADP/adp_formatted values indicate players expected to be drafted earlier',
                'usage' => 'Helps predict when players will be available in your draft',
            ],
        ];
    }

    public function execute(array $arguments): mixed
    {
        // Validate input arguments
        $validator = Validator::make($arguments, [
            'user_id' => ['required', 'string'],
            'draft_id' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            throw new JsonRpcErrorException(
                message: 'Validation failed: '.$validator->errors()->first(),
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $userId = $arguments['user_id'];
        $draftId = $arguments['draft_id'];
        $limit = $arguments['limit'] ?? 10;

        // Step 1: Fetch draft details
        $draftData = $this->fetchDraftData($draftId);
        if (! is_array($draftData)) {
            throw new JsonRpcErrorException(
                message: 'Invalid draft data received from API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        // Step 2: Fetch draft picks to see who's already drafted
        $draftPicks = $this->fetchDraftPicks($draftId);

        // Step 3: Find user's roster in the draft
        $userRoster = $this->findUserRoster($draftData, $userId);

        // Step 4: Analyze draft state and user's next pick
        $draftAnalysis = $this->analyzeDraftState($draftData, $draftPicks, $userRoster);

        // Step 5: Get user's current roster composition
        $rosterComposition = $this->analyzeRosterComposition($draftPicks, $userRoster);

        // Step 6: Determine positional needs based on league settings
        $positionalNeeds = $this->determinePositionalNeeds($draftData, $rosterComposition, $draftAnalysis);

        // Step 7: Get available players and generate suggestions
        $suggestions = $this->generateSuggestions($draftPicks, $positionalNeeds, $limit);

        return [
            'success' => true,
            'data' => [
                'suggestions' => $suggestions,
                'draft_analysis' => $draftAnalysis,
                'roster_composition' => $rosterComposition,
                'positional_needs' => $positionalNeeds,
            ],
            'metadata' => [
                'user_id' => $userId,
                'draft_id' => $draftId,
                'draft_type' => $draftData['type'] ?? 'unknown',
                'scoring_type' => $draftData['scoring_type'] ?? 'unknown',
                'season_type' => $draftData['season_type'] ?? 'unknown',
                'suggestions_count' => count($suggestions),
                'executed_at' => now()->toISOString(),
            ],
            'adp_guide' => [
                'data_types' => [
                    'adp' => 'Regular numeric value where lower numbers indicate players drafted earlier',
                    'adp_formatted' => 'Human-readable string showing round and pick (e.g., "1.05", "2.12")',
                    'adp_high' => 'Highest ADP value across different sources',
                    'adp_low' => 'Lowest ADP value across different sources',
                    'adp_stdev' => 'Standard deviation showing ADP consistency',
                ],
                'adp_formatted_interpretation' => 'String format showing round and pick position',
                'adp_formatted_format_breakdown' => [
                    'whole_number' => 'Draft round (1 = Round 1, 2 = Round 2, etc.)',
                    'decimal_portion' => 'Pick within round (.05 = Pick 5, .12 = Pick 12, etc.)',
                ],
                'adp_formatted_examples' => [
                    '1.05' => 'Round 1, Pick 5',
                    '2.12' => 'Round 2, Pick 12',
                    '5.08' => 'Round 5, Pick 8',
                    '12.03' => 'Round 12, Pick 3',
                ],
                'usage_tips' => [
                    'Lower adp/adp_formatted values = drafted earlier',
                    'Compare your draft position to ADP to assess availability',
                    'Look for value picks (players going later than their ADP)',
                    'Use ADP range (adp_high/adp_low) to understand variability',
                    'Lower adp_stdev indicates more consistent ADP across sources',
                ],
            ],
        ];
    }

    /**
     * Fetch draft data from Sleeper API
     */
    private function fetchDraftData(string $draftId): array
    {
        $response = Http::get("https://api.sleeper.app/v1/draft/{$draftId}");

        return $response->json();
    }

    /**
     * Fetch draft picks from Sleeper API
     */
    private function fetchDraftPicks(string $draftId): array
    {
        $response = Http::get("https://api.sleeper.app/v1/draft/{$draftId}/picks");

        if (! $response->successful()) {
            throw new JsonRpcErrorException(
                message: 'Failed to fetch draft picks from Sleeper API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        return $response->json();
    }

    /**
     * Find user's roster in the draft
     */
    private function findUserRoster(array $draftData, string $userId): ?array
    {
        $draftOrder = $draftData['draft_order'] ?? [];

        // Handle both array and object formats for draft_order
        if (is_array($draftOrder)) {
            // Array format: ["user_id" => roster_id]
            if (isset($draftOrder[$userId])) {
                return [
                    'user_id' => $userId,
                    'roster_id' => $draftOrder[$userId],
                    'draft_slot' => $draftOrder[$userId],
                ];
            }
        } elseif (is_object($draftOrder)) {
            // Object format: {"user_id": roster_id}
            if (isset($draftOrder->{$userId})) {
                return [
                    'user_id' => $userId,
                    'roster_id' => $draftOrder->{$userId},
                    'draft_slot' => $draftOrder->{$userId},
                ];
            }
        } else {
            logger('DraftPicksTool: draft_order is neither array nor object', [
                'draft_order_type' => gettype($draftOrder),
                'draft_order_value' => $draftOrder,
                'draft_id' => $draftData['draft_id'] ?? 'unknown',
                'user_id' => $userId,
            ]);

            return null;
        }

        // If we reach here, user was not found in draft_order
        logger('DraftPicksTool: User not found in draft_order', [
            'user_id' => $userId,
            'draft_order' => $draftOrder,
            'draft_id' => $draftData['draft_id'] ?? 'unknown',
        ]);

        return null;
    }

    /**
     * Analyze draft state and determine when user picks next
     */
    private function analyzeDraftState(array $draftData, array $draftPicks, array $userRoster): array
    {
        $totalPicks = is_array($draftPicks) ? count($draftPicks) : 0;
        $draftOrder = $draftData['draft_order'] ?? [];

        // Handle both array and object formats for draft_order
        if (is_array($draftOrder)) {
            $totalRosters = count($draftOrder);
        } elseif (is_object($draftOrder)) {
            $totalRosters = count((array) $draftOrder);
        } else {
            logger('DraftPicksTool: draft_order is neither array nor object in analyzeDraftState', [
                'draft_order_type' => gettype($draftOrder),
                'draft_order_value' => $draftOrder,
                'draft_id' => $draftData['draft_id'] ?? 'unknown',
            ]);
            $totalRosters = 0;
        }

        // Prevent division by zero
        if ($totalRosters <= 0) {
            logger('DraftPicksTool: No rosters found or draft_order is empty', [
                'total_rosters' => $totalRosters,
                'draft_id' => $draftData['draft_id'] ?? 'unknown',
            ]);

            return [
                'current_round' => 1,
                'pick_in_round' => 1,
                'total_picks_made' => $totalPicks,
                'is_users_turn' => false,
                'picks_until_user_turn' => 0,
                'user_draft_position' => $userRoster['roster_id'] ?? 1,
                'user_pick_in_round' => 1,
                'draft_type' => $draftData['type'] ?? 'snake',
                'is_snake_draft' => ($draftData['type'] ?? 'snake') === 'snake',
                'total_rosters' => $totalRosters,
            ];
        }

        $currentRound = intval($totalPicks / $totalRosters) + 1;
        $pickInRound = ($totalPicks % $totalRosters) + 1;
        $draftType = $draftData['type'] ?? 'snake';
        $isSnake = $draftType === 'snake';

        // Calculate user's draft position
        $userDraftPosition = $userRoster['roster_id'] ?? 1;

        // For snake draft, alternate the pick order each round
        $userPickInRound = $userDraftPosition;
        if ($isSnake && $currentRound % 2 === 0) {
            $userPickInRound = $totalRosters - $userDraftPosition + 1;
        }

        // Determine if it's user's turn or when they pick next
        $isUsersTurn = $pickInRound === $userPickInRound;
        $picksUntilUserTurn = $isUsersTurn ? 0 :
            (($userPickInRound - $pickInRound + $totalRosters) % $totalRosters);

        return [
            'current_round' => $currentRound,
            'pick_in_round' => $pickInRound,
            'total_picks_made' => $totalPicks,
            'is_users_turn' => $isUsersTurn,
            'picks_until_user_turn' => $picksUntilUserTurn,
            'user_draft_position' => $userDraftPosition,
            'user_pick_in_round' => $userPickInRound,
            'draft_type' => $draftType,
            'is_snake_draft' => $isSnake,
            'total_rosters' => $totalRosters,
        ];
    }

    /**
     * Analyze user's current roster composition
     */
    private function analyzeRosterComposition(array $draftPicks, array $userRoster): array
    {
        // Validate that draftPicks is an array
        if (! is_array($draftPicks)) {
            logger('DraftPicksTool: draftPicks is not an array in analyzeRosterComposition', [
                'draft_picks_type' => gettype($draftPicks),
                'draft_picks_value' => $draftPicks,
                'user_roster_id' => $userRoster['roster_id'] ?? 'unknown',
            ]);
            $draftPicks = [];
        }

        $rosterId = $userRoster['roster_id'] ?? null;
        if (! $rosterId) {
            logger('DraftPicksTool: No roster_id found in userRoster', [
                'user_roster' => $userRoster,
            ]);
            $userPicks = [];
        } else {
            $userPicks = array_filter($draftPicks, function ($pick) use ($rosterId) {
                // Ensure pick is an array before accessing its keys
                return is_array($pick) && isset($pick['roster_id']) && $pick['roster_id'] == $rosterId;
            });
        }

        $composition = [
            'QB' => 0, 'RB' => 0, 'WR' => 0, 'TE' => 0, 'K' => 0, 'DEF' => 0,
        ];

        $playerIds = array_column($userPicks, 'player_id');

        if (! empty($playerIds)) {
            // Get player positions from our database
            $players = Player::whereIn('player_id', $playerIds)->get();

            foreach ($players as $player) {
                $position = $player->position ?? 'UNKNOWN';
                if (isset($composition[$position])) {
                    $composition[$position]++;
                }
            }
        }

        return [
            'by_position' => $composition,
            'total_picks' => count($userPicks),
            'drafted_players' => $playerIds,
        ];
    }

    /**
     * Determine positional needs based on league settings and current roster
     */
    private function determinePositionalNeeds(array $draftData, array $rosterComposition, array $draftAnalysis): array
    {
        // Standard positional requirements (can be customized based on league settings)
        $standardRequirements = [
            'QB' => ['starters' => 1, 'bench' => 1, 'priority' => 5],
            'RB' => ['starters' => 2, 'bench' => 3, 'priority' => 1],
            'WR' => ['starters' => 2, 'bench' => 3, 'priority' => 2],
            'TE' => ['starters' => 1, 'bench' => 1, 'priority' => 4],
            'K' => ['starters' => 1, 'bench' => 0, 'priority' => 7],
            'DEF' => ['starters' => 1, 'bench' => 0, 'priority' => 6],
        ];

        $needs = [];
        $currentRound = $draftAnalysis['current_round'];

        foreach ($standardRequirements as $position => $req) {
            $drafted = $rosterComposition['by_position'][$position] ?? 0;
            $totalNeeded = $req['starters'] + $req['bench'];
            $stillNeeded = max(0, $totalNeeded - $drafted);

            // Adjust priority based on round and current needs
            $urgency = $this->calculateUrgency($position, $drafted, $req, $currentRound);

            if ($stillNeeded > 0 || $urgency > 0) {
                $needs[$position] = [
                    'drafted' => $drafted,
                    'starters_needed' => max(0, $req['starters'] - $drafted),
                    'bench_needed' => max(0, $req['bench'] - max(0, $drafted - $req['starters'])),
                    'total_needed' => $stillNeeded,
                    'priority' => $req['priority'],
                    'urgency' => $urgency,
                ];
            }
        }

        // Sort by urgency then by priority
        uasort($needs, function ($a, $b) {
            if ($a['urgency'] == $b['urgency']) {
                return $a['priority'] <=> $b['priority'];
            }

            return $b['urgency'] <=> $a['urgency'];
        });

        return $needs;
    }

    /**
     * Calculate urgency score for a position based on draft state
     */
    private function calculateUrgency(string $position, int $drafted, array $requirements, int $currentRound): float
    {
        $urgency = 0;

        // High urgency if no starters drafted yet
        if ($drafted < $requirements['starters']) {
            $urgency += 10;
        }

        // Increase urgency as draft progresses for key positions
        if (in_array($position, ['RB', 'WR']) && $currentRound > 6) {
            $urgency += 5;
        }

        // Lower urgency for kickers and defense early in draft
        if (in_array($position, ['K', 'DEF']) && $currentRound < 10) {
            $urgency -= 5;
        }

        return max(0, $urgency);
    }

    /**
     * Generate player suggestions based on available players and positional needs
     */
    private function generateSuggestions(array $draftPicks, array $positionalNeeds, int $limit): array
    {
        // Validate draftPicks is an array and extract drafted player IDs safely
        if (! is_array($draftPicks)) {
            logger('DraftPicksTool: draftPicks is not an array in generateSuggestions', [
                'draft_picks_type' => gettype($draftPicks),
                'positional_needs_count' => count($positionalNeeds),
            ]);
            $draftPicks = [];
        }

        // Get list of already drafted player IDs safely
        $draftedPlayerIds = [];
        foreach ($draftPicks as $pick) {
            if (is_array($pick) && isset($pick['player_id']) && $pick['player_id']) {
                $draftedPlayerIds[] = $pick['player_id'];
            }
        }

        $suggestions = [];

        // Get top positions by urgency/priority
        $topPositions = array_slice(array_keys($positionalNeeds), 0, 3, true);

        foreach ($topPositions as $position) {
            // Get available players for this position ordered by ADP
            $availablePlayers = Player::where('position', $position)
                ->whereNotNull('adp')
                ->whereNotIn('player_id', $draftedPlayerIds)
                ->orderBy('adp', 'asc')
                ->limit($limit)
                ->get();

            foreach ($availablePlayers as $player) {
                $suggestions[] = [
                    'player' => new PlayerResource($player),
                    'recommendation_reason' => $this->getRecommendationReason($player, $positionalNeeds[$position]),
                    'positional_need' => $positionalNeeds[$position],
                ];

                if (count($suggestions) >= $limit) {
                    break 2;
                }
            }
        }

        // If we still need more suggestions, add some best available players
        if (count($suggestions) < $limit) {
            $remainingSlots = $limit - count($suggestions);
            $bestAvailable = Player::whereNotNull('adp')
                ->whereNotIn('player_id', $draftedPlayerIds)
                ->whereNotIn('player_id', array_column($suggestions, 'player.player_id'))
                ->orderBy('adp', 'asc')
                ->limit($remainingSlots)
                ->get();

            foreach ($bestAvailable as $player) {
                $suggestions[] = [
                    'player' => new PlayerResource($player),
                    'recommendation_reason' => 'Best available player by ADP',
                    'positional_need' => null,
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Generate recommendation reason for a player
     */
    private function getRecommendationReason(Player $player, array $positionalNeed): string
    {
        $reasons = [];

        if ($positionalNeed['starters_needed'] > 0) {
            $reasons[] = "Need {$positionalNeed['starters_needed']} more starter(s) at {$player->position}";
        }

        if ($positionalNeed['urgency'] > 5) {
            $reasons[] = 'High priority position';
        }

        if ($player->adp && $player->adp <= 50) {
            $reasons[] = "Elite player (ADP: {$player->adp})";
        } elseif ($player->adp && $player->adp <= 100) {
            $reasons[] = "Quality starter (ADP: {$player->adp})";
        }

        return implode(', ', $reasons) ?: "Solid option at {$player->position}";
    }
}
