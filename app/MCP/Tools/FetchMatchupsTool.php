<?php

namespace App\MCP\Tools;

use Illuminate\Support\Facades\Http;
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
        return 'Get matchups for a league and week, returning raw matchup data supplemented with basic user info.';
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
        $leagueId = $arguments['league_id'] ?? null;
        $week = $arguments['week'] ?? null;
        $sport = $arguments['sport'] ?? 'nfl';

        if (! is_string($leagueId) || $leagueId === '') {
            throw new JsonRpcErrorException(
                message: 'league_id is required',
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        if ($week === null) {
            $week = $this->getCurrentWeek($sport);
        }

        // Fetch matchups from Sleeper API
        $matchups = $this->fetchMatchups($leagueId, (int) $week);

        // Supplement with user info (via league users and rosters)
        $supplemented = $this->supplementWithUsers($leagueId, $matchups);

        return [
            'league_id' => $leagueId,
            'week' => (int) $week,
            'matchups' => $supplemented,
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

    private function supplementWithUsers(string $leagueId, array $matchups): array
    {
        if ($matchups === []) {
            return [];
        }

        // Fetch league users and rosters once
        $usersResponse = Sleeper::leagues()->users($leagueId);
        $users = $usersResponse->successful() ? (array) $usersResponse->json() : [];

        $rostersResponse = Sleeper::leagues()->rosters($leagueId);
        $rosters = $rostersResponse->successful() ? (array) $rostersResponse->json() : [];

        // Build maps
        $ownerIdByRosterId = [];
        foreach ($rosters as $roster) {
            $rid = $roster['roster_id'] ?? null;
            if ($rid !== null) {
                $ownerIdByRosterId[$rid] = $roster['owner_id'] ?? null;
            }
        }

        $userById = [];
        foreach ($users as $user) {
            $uid = $user['user_id'] ?? null;
            if ($uid !== null) {
                $userById[$uid] = $user;
            }
        }

        // Supplement matchups
        $result = [];
        foreach ($matchups as $matchup) {
            $starters = (array) ($matchup['starters'] ?? []);
            $players = (array) ($matchup['players'] ?? []);
            $bench = array_values(array_diff($players, $starters));

            $rosterId = $matchup['roster_id'] ?? null;
            $ownerId = $rosterId !== null ? ($ownerIdByRosterId[$rosterId] ?? null) : null;
            $user = $ownerId !== null ? ($userById[$ownerId] ?? null) : null;

            $result[] = [
                'matchup_id' => $matchup['matchup_id'] ?? null,
                'roster_id' => $rosterId,
                'points' => $matchup['points'] ?? null,
                'custom_points' => $matchup['custom_points'] ?? null,
                'starters' => $starters,
                'players' => $players,
                'bench' => $bench,
                'user' => $user ? [
                    'user_id' => $user['user_id'] ?? null,
                    'username' => $user['username'] ?? null,
                    'display_name' => $user['display_name'] ?? null,
                    'team_name' => $user['metadata']['team_name'] ?? $user['display_name'] ?? $user['username'] ?? (
                        $user['user_id'] ?? 'Unknown'
                    ),
                    'avatar' => $user['avatar'] ?? null,
                ] : null,
            ];
        }

        return $result;
    }
}
