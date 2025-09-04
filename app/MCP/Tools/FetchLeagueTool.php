<?php

namespace App\MCP\Tools;

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
        return [];
    }

    public function execute(array $arguments): mixed
    {
        $userId = $arguments['user_id'];
        $leagueIdentifier = $arguments['league_identifier'];
        $sport = $arguments['sport'] ?? 'nfl';
        $season = $arguments['season'] ?? $this->getCurrentSeason($sport);

        // Get user's leagues and find the target league
        $leagues = $this->apiCall(fn () => Sleeper::users()->leagues($userId, $sport, $season), 'fetch user leagues');
        $targetLeague = collect($leagues)->first(fn ($league) => $league['league_id'] === $leagueIdentifier ||
            strcasecmp($league['name'] ?? '', $leagueIdentifier) === 0
        );

        if (! $targetLeague) {
            throw new JsonRpcErrorException(
                message: "League '{$leagueIdentifier}' not found",
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $leagueId = $targetLeague['league_id'];

        // Get league details and users with rosters
        $league = $this->apiCall(fn () => Sleeper::leagues()->get($leagueId), 'fetch league details');
        $users = $this->apiCall(fn () => Sleeper::leagues()->users($leagueId), 'fetch league users');
        $rosters = $this->apiCall(fn () => Sleeper::leagues()->rosters($leagueId), 'fetch league rosters');

        // Map rosters to users
        $rostersByUserId = collect($rosters)->keyBy('owner_id');
        $enhancedUsers = collect($users)->map(function ($user) use ($rostersByUserId) {
            $userId = $user['user_id'];
            $roster = $rostersByUserId->get($userId);

            return [
                'user_id' => $userId,
                'username' => $user['username'] ?? null,
                'display_name' => $user['display_name'] ?? null,
                'team_name' => $user['metadata']['team_name'] ?? $user['display_name'] ?? $user['username'] ?? "Team {$userId}",
                'wins' => $roster['settings']['wins'] ?? 0,
                'losses' => $roster['settings']['losses'] ?? 0,
                'fpts' => $roster['settings']['fpts'] ?? 0,
            ];
        });

        return [
            'league' => $league,
            'users' => $enhancedUsers,
        ];
    }

    private function getCurrentSeason(string $sport): string
    {
        try {
            $response = Sleeper::state()->current($sport);
            $state = $response->successful() ? $response->json() : [];

            return (string) ($state['league_season'] ?? $state['season'] ?? date('Y'));
        } catch (\Exception) {
            return date('Y');
        }
    }

    private function apiCall(callable $request, string $action): array
    {
        $response = $request();

        if (! $response->successful()) {
            throw new JsonRpcErrorException(
                message: "Failed to {$action}",
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        return $response->json();
    }
}
