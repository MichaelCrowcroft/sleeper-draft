<?php

namespace App\MCP\Tools;

use App\Actions\Sleeper\FetchLeagueUsers;
use App\Actions\Sleeper\FetchRosters;
use App\Actions\Sleeper\GetSeasonState;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;
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
                    'type' => 'integer',
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
        $league_id = $arguments['league_id'] ?? null;
        $week = $arguments['week'] ?? null;
        $sport = $arguments['sport'] ?? 'nfl';

        if ($week === null) {
            $week = new GetSeasonState($sport)->execute()['week'];
        }

        $matchups = Sleeper::leagues()->matchups($league_id, $week)->json();
        if($matchups === []) {
            return [];
        }
        $users = Sleeper::leagues()->users($league_id)->json();
        $rosters = Sleeper::leagues()->rosters($league_id)->json();

        foreach($matchups as $matchup) {
            $matchup['owner_id'] = $matchup['roster_id'] ?? null;
        }

        // Group into head-to-head matchups with two teams per matchup_id
        $paired = $this->pairByMatchupId($supplemented);

        return $this->buildResponse(
            data: [
                'league_id' => $leagueId,
                'week' => (int) $week,
                'matchups' => $paired,
            ],
            count: count($paired),
            message: 'Fetched '.count($paired).' matchups for week '.(int) $week,
            metadata: [
                'sport' => $sport,
            ]
        );
    }

    private function supplementWithUsers(string $leagueId, array $matchups): array
    {
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
