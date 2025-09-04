<?php

namespace App\MCP\Tools;

use Illuminate\Support\Facades\Http;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchUserMatchupTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-user-matchup';
    }

    public function description(): string
    {
        return 'Get a user\'s specific matchup for a league and week, returning that user and their opponent with basic user info.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'league_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper league ID',
                ],
                'user_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper user ID to find the matchup for',
                ],
                'week' => [
                    'anyOf' => [
                        ['type' => 'integer', 'minimum' => 1, 'maximum' => 18],
                        ['type' => 'null'],
                    ],
                    'description' => 'Week number (defaults to current week for the sport)',
                ],
                'sport' => [
                    'type' => 'string',
                    'description' => 'Sport type (default: nfl)',
                    'default' => 'nfl',
                ],
            ],
            'required' => ['league_id', 'user_id'],
        ];
    }

    public function annotations(): array
    {
        return [];
    }

    public function execute(array $arguments): mixed
    {
        $arguments = $this->normalizeArguments($arguments);
        $leagueId = $arguments['league_id'] ?? null;
        $userId = $arguments['user_id'] ?? null;
        $week = $arguments['week'] ?? null;
        $sport = $arguments['sport'] ?? 'nfl';

        if (! is_string($leagueId) || $leagueId === '') {
            throw new JsonRpcErrorException(
                message: 'league_id is required',
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        if (! is_string($userId) || $userId === '') {
            throw new JsonRpcErrorException(
                message: 'user_id is required',
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        if ($week === null) {
            $week = $this->getCurrentWeek($sport);
        }

        $matchups = $this->fetchMatchups($leagueId, (int) $week);

        // Build maps for users/rosters
        [$userById, $ownerIdByRosterId] = $this->buildLeagueMaps($leagueId);

        // Find roster for provided user
        $rosterId = array_search($userId, $ownerIdByRosterId, true);
        if ($rosterId === false) {
            throw new JsonRpcErrorException(
                message: 'User not found in this league',
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        // Find this user\'s matchup
        $userMatchup = null;
        foreach ($matchups as $m) {
            if (($m['roster_id'] ?? null) === $rosterId) {
                $userMatchup = $m;
                break;
            }
        }

        if (! $userMatchup || ! isset($userMatchup['matchup_id'])) {
            throw new JsonRpcErrorException(
                message: 'Matchup not found for user in this week',
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $matchupId = $userMatchup['matchup_id'];

        // Find opponent (same matchup_id, different roster)
        $opponentMatchup = null;
        foreach ($matchups as $m) {
            if (($m['matchup_id'] ?? null) === $matchupId && ($m['roster_id'] ?? null) !== $rosterId) {
                $opponentMatchup = $m;
                break;
            }
        }

        $teams = [
            $this->enrich($userMatchup, $ownerIdByRosterId, $userById),
        ];

        if ($opponentMatchup) {
            $teams[] = $this->enrich($opponentMatchup, $ownerIdByRosterId, $userById);
        }

        return [
            'league_id' => $leagueId,
            'week' => (int) $week,
            'matchup_id' => $matchupId,
            'teams' => $teams,
        ];
    }

    private function normalizeArguments(array $arguments): array
    {
        if (count($arguments) === 1 && array_key_exists(0, $arguments) && is_string($arguments[0])) {
            $raw = trim((string) $arguments[0]);
            $raw = preg_replace('/^\s*Arguments\s*/i', '', $raw ?? '');
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $arguments = $decoded;
            } else {
                $pairs = [];
                $pattern = '/([A-Za-z0-9_]+)\s*(?::|=)?\s*(?:\"([^\"]*)\"|\'([^\']*)\'|([0-9]+)|(true|false|null))/i';
                if (preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $m) {
                        $key = $m[1];
                        $val = null;
                        if (($m[2] ?? '') !== '') {
                            $val = $m[2];
                        } elseif (($m[3] ?? '') !== '') {
                            $val = $m[3];
                        } elseif (($m[4] ?? '') !== '') {
                            $val = (int) $m[4];
                        } elseif (($m[5] ?? '') !== '') {
                            $lower = strtolower($m[5]);
                            $val = $lower === 'true' ? true : ($lower === 'false' ? false : null);
                        }
                        $pairs[$key] = $val;
                    }
                }
                if ($pairs !== []) {
                    $arguments = $pairs;
                }
            }
        }

        $aliases = [
            'leagueId' => 'league_id',
            'userId' => 'user_id',
            'weekNumber' => 'week',
            'sportType' => 'sport',
        ];
        $normalized = [];
        foreach ($arguments as $key => $value) {
            $normalized[$aliases[$key] ?? $key] = $value;
        }

        if (isset($normalized['week']) && is_string($normalized['week'])) {
            $normalized['week'] = (int) $normalized['week'];
        }
        if (isset($normalized['league_id'])) {
            $normalized['league_id'] = (string) $normalized['league_id'];
        }
        if (isset($normalized['user_id'])) {
            $normalized['user_id'] = (string) $normalized['user_id'];
        }
        if (isset($normalized['sport'])) {
            $normalized['sport'] = (string) $normalized['sport'];
        }

        return $normalized;
    }

    private function getCurrentWeek(string $sport): int
    {
        try {
            $response = Sleeper::state()->current($sport);
            if (! $response->successful()) {
                return 1;
            }
            $state = $response->json();

            return (int) ($state['week'] ?? 1);
        } catch (\Exception) {
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

        return is_array($matchups) ? $matchups : [];
    }

    private function buildLeagueMaps(string $leagueId): array
    {
        $usersResponse = Sleeper::leagues()->users($leagueId);
        $users = $usersResponse->successful() ? (array) $usersResponse->json() : [];

        $rostersResponse = Sleeper::leagues()->rosters($leagueId);
        $rosters = $rostersResponse->successful() ? (array) $rostersResponse->json() : [];

        $userById = [];
        foreach ($users as $user) {
            $uid = $user['user_id'] ?? null;
            if ($uid !== null) {
                $userById[$uid] = $user;
            }
        }

        $ownerIdByRosterId = [];
        foreach ($rosters as $roster) {
            $rid = $roster['roster_id'] ?? null;
            if ($rid !== null) {
                $ownerIdByRosterId[$rid] = $roster['owner_id'] ?? null;
            }
        }

        return [$userById, $ownerIdByRosterId];
    }

    private function enrich(array $matchup, array $ownerIdByRosterId, array $userById): array
    {
        $starters = (array) ($matchup['starters'] ?? []);
        $players = (array) ($matchup['players'] ?? []);
        $bench = array_values(array_diff($players, $starters));

        $rosterId = $matchup['roster_id'] ?? null;
        $ownerId = $rosterId !== null ? ($ownerIdByRosterId[$rosterId] ?? null) : null;
        $user = $ownerId !== null ? ($userById[$ownerId] ?? null) : null;

        return [
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
}
