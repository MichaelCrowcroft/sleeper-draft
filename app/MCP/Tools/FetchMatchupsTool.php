<?php

namespace App\MCP\Tools;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchMatchupsTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-matchups';
    }

    public function description(): string
    {
        return 'Fetch basic matchup information for a league for a given week (defaults to current week), showing the two users in each matchup and league details. Use the roster tool for detailed team information.';
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
            'title' => 'Fetch League Matchups',
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

        // Get basic matchup information with user details
        $basicMatchups = $this->getBasicMatchupInfo($filteredMatchups, $leagueId);

        $response = [
            'success' => true,
            'data' => [
                'matchups' => $basicMatchups,
                'week' => $week,
                'league_id' => $leagueId,
                'filtered_by_user' => $userId !== null,
                'user_filter' => $userId ? [
                    'user_id' => $userId,
                    'username' => $username,
                ] : null,
            ],
            'count' => count($basicMatchups),
            'metadata' => [
                'league_id' => $leagueId,
                'week' => $week,
                'sport' => $sport,
                'total_matchups_in_week' => count($matchups),
                'filtered_matchups' => count($basicMatchups),
                'executed_at' => now()->toISOString(),
            ],
        ];

        return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
            logger('FetchMatchupsTool: Failed to fetch current week, defaulting to 1', [
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
            logger('FetchMatchupsTool: Failed to resolve username to user_id', [
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
            logger('FetchMatchupsTool: Failed to filter matchups by user', [
                'user_id' => $userId,
                'league_id' => $leagueId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function getBasicMatchupInfo(array $matchups, string $leagueId): array
    {
        if (empty($matchups)) {
            return [];
        }

        try {
            // Fetch users for the league to get user details
            $usersResponse = Sleeper::leagues()->users($leagueId);
            $users = $usersResponse->successful() ? $usersResponse->json() : [];

            // Create user lookup map
            $userMap = [];
            if (is_array($users)) {
                foreach ($users as $user) {
                    $userMap[$user['user_id'] ?? null] = $user;
                }
            }

            // Simplify matchups to basic info only
            $basicMatchups = [];
            foreach ($matchups as $matchup) {
                $rosterId = $matchup['roster_id'] ?? null;

                // Get user details for this roster (we'll need to fetch rosters to map roster_id to user_id)
                $user = null;
                if ($rosterId) {
                    try {
                        $rostersResponse = Sleeper::leagues()->rosters($leagueId);
                        if ($rostersResponse->successful()) {
                            $rosters = $rostersResponse->json();
                            foreach ($rosters as $roster) {
                                if (($roster['roster_id'] ?? null) == $rosterId) {
                                    $ownerId = $roster['owner_id'] ?? null;
                                    $user = $userMap[$ownerId] ?? null;
                                    break;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        logger('FetchMatchupsTool: Failed to fetch roster details', [
                            'roster_id' => $rosterId,
                            'league_id' => $leagueId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Create simplified matchup entry
                $basicMatchup = [
                    'matchup_id' => $matchup['matchup_id'] ?? null,
                    'roster_id' => $rosterId,
                    'points' => $matchup['points'] ?? null,
                    'user' => $user ? [
                        'user_id' => $user['user_id'] ?? null,
                        'username' => $user['username'] ?? null,
                        'display_name' => $user['display_name'] ?? null,
                        'team_name' => $user['metadata']['team_name'] ?? $user['display_name'] ?? $user['username'] ?? 'Team '.($user['user_id'] ?? 'Unknown'),
                    ] : null,
                ];

                $basicMatchups[] = $basicMatchup;
            }

            return $basicMatchups;
        } catch (\Exception $e) {
            logger('FetchMatchupsTool: Failed to get basic matchup info', [
                'league_id' => $leagueId,
                'error' => $e->getMessage(),
            ]);

            // Return basic structure even on error
            return array_map(function ($matchup) {
                return [
                    'matchup_id' => $matchup['matchup_id'] ?? null,
                    'roster_id' => $matchup['roster_id'] ?? null,
                    'points' => $matchup['points'] ?? null,
                    'user' => null,
                ];
            }, $matchups);
        }
    }
}
