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
        return 'Fetch matchups for a league and week, returning raw matchup data supplemented with basic user info. Entries that share the same `matchup_id` are opponents in the same head-to-head matchup.';
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
            'notes' => 'Two records with the same `matchup_id` represent opposing teams in the same matchup.',
        ];
    }

    public function execute(array $arguments): mixed
    {
        $arguments = $this->normalizeArguments($arguments);
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

        // Group into head-to-head matchups with two teams per matchup_id
        $paired = $this->pairByMatchupId($supplemented);

        return [
            'league_id' => $leagueId,
            'week' => (int) $week,
            'matchups' => $paired,
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
            $rosterId = $matchup['roster_id'] ?? null;
            $ownerId = $rosterId !== null ? ($ownerIdByRosterId[$rosterId] ?? null) : null;
            $user = $ownerId !== null ? ($userById[$ownerId] ?? null) : null;

            $result[] = [
                'matchup_id' => $matchup['matchup_id'] ?? null,
                'points' => $matchup['points'] ?? null,
                'custom_points' => $matchup['custom_points'] ?? null,
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

    private function pairByMatchupId(array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        $byMatchupId = [];
        foreach ($entries as $entry) {
            $mid = $entry['matchup_id'] ?? null;
            if ($mid === null) {
                // Skip entries without a matchup_id
                continue;
            }
            if (! isset($byMatchupId[$mid])) {
                $byMatchupId[$mid] = [];
            }
            $byMatchupId[$mid][] = $entry;
        }

        $formatted = [];
        foreach ($byMatchupId as $mid => $teams) {
            $formattedTeams = [];
            foreach ($teams as $team) {
                $formattedTeams[] = [
                    'points' => $team['points'] ?? null,
                    'custom_points' => $team['custom_points'] ?? null,
                    'user' => $team['user'] ?? null,
                ];
            }

            $formatted[] = [
                'matchup_id' => $mid,
                'teams' => array_values($formattedTeams),
            ];
        }

        // Ensure consistent ordering by matchup_id for predictability
        usort($formatted, fn ($a, $b) => ($a['matchup_id'] ?? 0) <=> ($b['matchup_id'] ?? 0));

        return $formatted;
    }
}
