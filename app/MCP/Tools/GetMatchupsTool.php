<?php

namespace App\MCP\Tools;

use App\Models\Player;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class GetMatchupsTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'get-matchups';
    }

    public function description(): string
    {
        return 'Get matchups for a league for a given week (defaults to current week). Optionally filter by username or user ID to return only that user\'s matchup.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'league_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper league ID to fetch matchups for',
                ],
                'week' => [
                    'anyOf' => [
                        ['type' => 'integer', 'minimum' => 1, 'maximum' => 18],
                        ['type' => 'null'],
                    ],
                    'description' => 'Week number to fetch matchups for (defaults to current week)',
                ],
                'user_id' => [
                    'anyOf' => [
                        ['type' => 'string'],
                        ['type' => 'null'],
                    ],
                    'description' => 'Optional: Sleeper user ID to filter matchups for a specific user',
                ],
                'username' => [
                    'anyOf' => [
                        ['type' => 'string'],
                        ['type' => 'null'],
                    ],
                    'description' => 'Optional: Username to filter matchups for a specific user (alternative to user_id)',
                ],
                'sport' => [
                    'type' => 'string',
                    'description' => 'Sport type (default: nfl)',
                    'default' => 'nfl',
                ],
            ],
            'required' => ['league_id'],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Get League Matchups',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => true, // Makes API calls to Sleeper

            // Custom annotations
            'category' => 'fantasy-sports',
            'data_source' => 'external_api',
            'cache_recommended' => true,
        ];
    }

    public function execute(array $arguments): mixed
    {
        // Validate input arguments
        $validator = Validator::make($arguments, [
            'league_id' => ['required', 'string'],
            'week' => ['nullable', 'integer', 'min:1', 'max:18'],
            'user_id' => ['nullable', 'string'],
            'username' => ['nullable', 'string'],
            'sport' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            throw new JsonRpcErrorException(
                message: 'Validation failed: '.$validator->errors()->first(),
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $leagueId = $arguments['league_id'];
        $week = $arguments['week'] ?? null;
        $userId = $arguments['user_id'] ?? null;
        $username = $arguments['username'] ?? null;
        $sport = $arguments['sport'] ?? 'nfl';

        // Get current week if not provided
        if ($week === null) {
            $week = $this->getCurrentWeek($sport);
        }

        // Fetch matchups from Sleeper API
        $matchups = $this->fetchMatchups($leagueId, $week);

        // If username is provided but not user_id, resolve the user_id
        if ($username && ! $userId) {
            $userId = $this->resolveUsernameToUserId($username, $sport);
        }

        // Filter matchups by user if specified
        $filteredMatchups = $matchups;
        $userMatchup = null;
        if ($userId) {
            $userMatchup = $this->filterMatchupsByUser($matchups, $leagueId, $userId);
            $filteredMatchups = $userMatchup ? [$userMatchup] : [];
        }

        // Enhance matchups with user/roster details
        $enhancedMatchups = $this->enhanceMatchupsWithDetails($filteredMatchups, $leagueId);

        return [
            'success' => true,
            'data' => [
                'matchups' => $enhancedMatchups,
                'week' => $week,
                'league_id' => $leagueId,
                'filtered_by_user' => $userId !== null,
                'user_filter' => $userId ? [
                    'user_id' => $userId,
                    'username' => $username,
                ] : null,
            ],
            'count' => count($enhancedMatchups),
            'metadata' => [
                'league_id' => $leagueId,
                'week' => $week,
                'sport' => $sport,
                'total_matchups_in_week' => count($matchups),
                'filtered_matchups' => count($enhancedMatchups),
                'executed_at' => now()->toISOString(),
            ],
        ];
    }

    private function getCurrentWeek(string $sport): int
    {
        try {
            $response = Sleeper::state()->current($sport);

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to fetch current state from Sleeper API');
            }

            $state = $response->json();

            // Get the current week from the state
            return (int) ($state['week'] ?? 1);
        } catch (\Exception $e) {
            // Fallback to week 1 if we can't get the current week
            logger('GetMatchupsTool: Failed to fetch current week, defaulting to 1', [
                'sport' => $sport,
                'error' => $e->getMessage(),
            ]);

            return 1;
        }
    }

    private function fetchMatchups(string $leagueId, int $week): array
    {
        $response = Http::get("https://api.sleeper.app/v1/league/{$leagueId}/matchups/{$week}");

        if (! $response->successful()) {
            throw new JsonRpcErrorException(
                message: 'Failed to fetch matchups from Sleeper API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        $matchups = $response->json();

        if (! is_array($matchups)) {
            throw new JsonRpcErrorException(
                message: 'Invalid matchups data received from API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        return $matchups;
    }

    private function resolveUsernameToUserId(string $username, string $sport): ?string
    {
        try {
            $response = Sleeper::users()->get($username);

            if ($response->successful()) {
                $userData = $response->json();

                return $userData['user_id'] ?? null;
            }
        } catch (\Exception $e) {
            logger('GetMatchupsTool: Failed to resolve username to user_id', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function filterMatchupsByUser(array $matchups, string $leagueId, string $userId): ?array
    {
        // First, get all rosters for the league to find which roster_id belongs to the user
        try {
            $rostersResponse = Sleeper::leagues()->rosters($leagueId);
            if (! $rostersResponse->successful()) {
                return null;
            }

            $rosters = $rostersResponse->json();
            if (! is_array($rosters)) {
                return null;
            }

            // Find the roster_id for this user
            $userRosterId = null;
            foreach ($rosters as $roster) {
                if (($roster['owner_id'] ?? null) === $userId) {
                    $userRosterId = $roster['roster_id'] ?? null;
                    break;
                }
            }

            if ($userRosterId === null) {
                return null;
            }

            // Find the matchup containing this roster_id
            foreach ($matchups as $matchup) {
                if (($matchup['roster_id'] ?? null) == $userRosterId) {
                    return $matchup;
                }
            }
        } catch (\Exception $e) {
            logger('GetMatchupsTool: Failed to filter matchups by user', [
                'user_id' => $userId,
                'league_id' => $leagueId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function enhanceMatchupsWithDetails(array $matchups, string $leagueId): array
    {
        if (empty($matchups)) {
            return [];
        }

        try {
            // Fetch rosters and users for additional context
            $rostersResponse = Sleeper::leagues()->rosters($leagueId);
            $usersResponse = Sleeper::leagues()->users($leagueId);

            $rosters = $rostersResponse->successful() ? $rostersResponse->json() : [];
            $users = $usersResponse->successful() ? $usersResponse->json() : [];

            // Create lookup maps
            $rosterMap = [];
            $userMap = [];

            if (is_array($rosters)) {
                foreach ($rosters as $roster) {
                    $rosterMap[$roster['roster_id'] ?? null] = $roster;
                }
            }

            if (is_array($users)) {
                foreach ($users as $user) {
                    $userMap[$user['user_id'] ?? null] = $user;
                }
            }

            // Collect all player IDs from matchups to fetch player data
            $allPlayerIds = [];
            foreach ($matchups as $matchup) {
                // Extract player IDs from starters array
                if (isset($matchup['starters']) && is_array($matchup['starters'])) {
                    $allPlayerIds = array_merge($allPlayerIds, $matchup['starters']);
                }
                // Extract player IDs from players array (full roster)
                if (isset($matchup['players']) && is_array($matchup['players'])) {
                    $allPlayerIds = array_merge($allPlayerIds, $matchup['players']);
                }
            }

            // Remove duplicates and null values
            $uniquePlayerIds = array_unique(array_filter($allPlayerIds));

            // Fetch player data from database
            $playersMap = [];
            if (!empty($uniquePlayerIds)) {
                $players = Player::whereIn('player_id', $uniquePlayerIds)->get();
                foreach ($players as $player) {
                    $playersMap[$player->player_id] = $player;
                }
            }

            // Enhance matchups with player data
            $enhancedMatchups = [];
            foreach ($matchups as $matchup) {
                $rosterId = $matchup['roster_id'] ?? null;
                $roster = $rosterMap[$rosterId] ?? null;
                $ownerId = $roster['owner_id'] ?? null;
                $user = $userMap[$ownerId] ?? null;

                $enhancedMatchup = $matchup;
                $enhancedMatchup['roster_details'] = $roster;
                $enhancedMatchup['owner_details'] = $user ? [
                    'user_id' => $user['user_id'] ?? null,
                    'username' => $user['username'] ?? null,
                    'display_name' => $user['display_name'] ?? null,
                    'avatar' => $user['avatar'] ?? null,
                    'team_name' => $user['metadata']['team_name'] ?? $user['display_name'] ?? $user['username'] ?? 'Team '.$ownerId,
                ] : null;

                // Enhance starters with full player data
                if (isset($enhancedMatchup['starters']) && is_array($enhancedMatchup['starters'])) {
                    $enhancedMatchup['starters_data'] = [];
                    foreach ($enhancedMatchup['starters'] as $playerId) {
                        if (isset($playersMap[$playerId])) {
                            $enhancedMatchup['starters_data'][] = $playersMap[$playerId];
                        } else {
                            // Keep the original player ID if no data found
                            $enhancedMatchup['starters_data'][] = ['player_id' => $playerId];
                        }
                    }
                }

                // Enhance full roster with player data
                if (isset($enhancedMatchup['players']) && is_array($enhancedMatchup['players'])) {
                    $enhancedMatchup['players_data'] = [];
                    foreach ($enhancedMatchup['players'] as $playerId) {
                        if (isset($playersMap[$playerId])) {
                            $enhancedMatchup['players_data'][] = $playersMap[$playerId];
                        } else {
                            // Keep the original player ID if no data found
                            $enhancedMatchup['players_data'][] = ['player_id' => $playerId];
                        }
                    }
                }

                $enhancedMatchups[] = $enhancedMatchup;
            }

            return $enhancedMatchups;
        } catch (\Exception $e) {
            logger('GetMatchupsTool: Failed to enhance matchups with details', [
                'league_id' => $leagueId,
                'error' => $e->getMessage(),
            ]);

            return $matchups; // Return original matchups if enhancement fails
        }
    }
}
