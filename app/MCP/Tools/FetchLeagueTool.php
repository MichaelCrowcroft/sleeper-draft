<?php

namespace App\MCP\Tools;

use Illuminate\Support\Facades\Validator;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchLeagueTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'get-league';
    }

    public function description(): string
    {
        return 'Get League tool fetches all leagues for a user and returns one league based on the name or ID. It also fetches all users in the league and returns user information with username, display name, and team names.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'user_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper user ID to fetch leagues for',
                ],
                'league_identifier' => [
                    'type' => 'string',
                    'description' => 'League name or league ID to find and return',
                ],
                'sport' => [
                    'type' => 'string',
                    'description' => 'Sport type (default: nfl)',
                    'default' => 'nfl',
                ],
                'season' => [
                    'type' => 'string',
                    'description' => 'Season year (default: current season)',
                ],
            ],
            'required' => ['user_id', 'league_identifier'],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Get League Information',
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
            'user_id' => ['required', 'string'],
            'league_identifier' => ['required', 'string'],
            'sport' => ['nullable', 'string'],
            'season' => ['nullable', 'string'],
        ]);

        $userId = $arguments['user_id'];
        $leagueIdentifier = $arguments['league_identifier'];
        $sport = $arguments['sport'] ?? 'nfl';
        $season = $arguments['season'] ?? null;

        // Step 1: Get the current season if not provided
        if (! $season) {
            $season = $this->getCurrentSeason($sport);
        }

        // Step 2: Fetch all user's leagues for the season
        $userLeagues = $this->fetchUserLeagues($userId, $sport, $season);

        // Step 3: Find the specific league by name or ID
        $targetLeague = $this->findLeague($userLeagues, $leagueIdentifier);
        if (! $targetLeague) {
            throw new JsonRpcErrorException(
                message: "League with identifier '{$leagueIdentifier}' not found in user's leagues",
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        // Step 4: Fetch full league details
        $leagueId = $targetLeague['league_id'];
        $leagueDetails = $this->fetchLeagueDetails($leagueId);

        // Step 5: Fetch league users with enhanced information
        $leagueUsers = $this->fetchLeagueUsersWithDetails($leagueId);

        return [
            'success' => true,
            'data' => [
                'league' => $leagueDetails,
                'users' => $leagueUsers,
            ],
            'metadata' => [
                'user_id' => $userId,
                'league_id' => $leagueId,
                'league_name' => $leagueDetails['name'] ?? 'Unknown',
                'sport' => $sport,
                'season' => $season,
                'total_users' => count($leagueUsers),
                'executed_at' => now()->toISOString(),
            ],
        ];
    }

    private function getCurrentSeason(string $sport): string
    {
        try {
            $response = Sleeper::state()->current($sport);

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to fetch current season from Sleeper API');
            }

            $state = $response->json();

            return (string) ($state['league_season'] ?? $state['season'] ?? date('Y'));
        } catch (\Exception $e) {
            // Fallback to current year
            logger('GetLeagueTool: Failed to fetch current season, using current year', [
                'sport' => $sport,
                'error' => $e->getMessage(),
            ]);

            return date('Y');
        }
    }

    private function fetchUserLeagues(string $userId, string $sport, string $season): array
    {
        $response = Sleeper::users()->leagues($userId, $sport, $season);

        if (! $response->successful()) {
            throw new JsonRpcErrorException(
                message: 'Failed to fetch user leagues from Sleeper API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        $leagues = $response->json();

        if (! is_array($leagues)) {
            throw new JsonRpcErrorException(
                message: 'Invalid leagues data received from API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        return $leagues;
    }

    private function findLeague(array $leagues, string $identifier): ?array
    {
        foreach ($leagues as $league) {
            // Check by league ID
            if (isset($league['league_id']) && $league['league_id'] === $identifier) {
                return $league;
            }

            // Check by league name (case-insensitive)
            if (isset($league['name']) && strcasecmp($league['name'], $identifier) === 0) {
                return $league;
            }
        }

        return null;
    }

    private function fetchLeagueDetails(string $leagueId): array
    {
        $response = Sleeper::leagues()->get($leagueId);

        if (! $response->successful()) {
            throw new JsonRpcErrorException(
                message: 'Failed to fetch league details from Sleeper API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        $league = $response->json();

        if (! is_array($league)) {
            throw new JsonRpcErrorException(
                message: 'Invalid league data received from API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        return $league;
    }

    private function fetchLeagueUsersWithDetails(string $leagueId): array
    {
        // Fetch users
        $usersResponse = Sleeper::leagues()->users($leagueId);
        if (! $usersResponse->successful()) {
            throw new JsonRpcErrorException(
                message: 'Failed to fetch league users from Sleeper API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        $users = $usersResponse->json();
        if (! is_array($users)) {
            throw new JsonRpcErrorException(
                message: 'Invalid users data received from API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        // Fetch rosters to get team ownership information
        $rostersResponse = Sleeper::leagues()->rosters($leagueId);
        if (! $rostersResponse->successful()) {
            throw new JsonRpcErrorException(
                message: 'Failed to fetch league rosters from Sleeper API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        $rosters = $rostersResponse->json();
        if (! is_array($rosters)) {
            throw new JsonRpcErrorException(
                message: 'Invalid rosters data received from API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        // Create mapping of user_id to roster information
        $rostersByUserId = [];
        foreach ($rosters as $roster) {
            $ownerId = $roster['owner_id'] ?? null;
            if ($ownerId) {
                $rostersByUserId[$ownerId] = $roster;
            }
        }

        // Enhance users with roster and team information
        $enhancedUsers = [];
        foreach ($users as $user) {
            $userId = $user['user_id'] ?? null;
            $roster = $rostersByUserId[$userId] ?? null;

            $enhancedUser = [
                'user_id' => $userId,
                'username' => $user['username'] ?? null,
                'display_name' => $user['display_name'] ?? null,
                'avatar' => $user['avatar'] ?? null,
                'metadata' => $user['metadata'] ?? [],
                'team_name' => $user['metadata']['team_name'] ?? $user['display_name'] ?? $user['username'] ?? 'Team '.$userId,
                'roster' => null,
            ];

            // Add roster information if available
            if ($roster) {
                $enhancedUser['roster'] = [
                    'roster_id' => $roster['roster_id'] ?? null,
                    'owner_id' => $roster['owner_id'] ?? null,
                    'settings' => $roster['settings'] ?? [],
                    'wins' => $roster['settings']['wins'] ?? 0,
                    'losses' => $roster['settings']['losses'] ?? 0,
                    'ties' => $roster['settings']['ties'] ?? 0,
                    'fpts' => $roster['settings']['fpts'] ?? 0.0,
                    'fpts_against' => $roster['settings']['fpts_against'] ?? 0.0,
                ];
            }

            $enhancedUsers[] = $enhancedUser;
        }

        return $enhancedUsers;
    }
}
