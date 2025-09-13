<?php

namespace App\MCP\Tools;

use App\Actions\Matchups\EnrichMatchupsWithPlayerData;
use App\Actions\Matchups\FilterMatchups;
use App\Actions\Matchups\GetMatchupsWithOwners;
use App\Actions\Matchups\MergeEnrichedMatchupsWithRosterPositions;
use App\Actions\Sleeper\GetSeasonState;
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
        return 'Fetch enriched matchups for a league and week, including player data, projections, win probabilities, and confidence intervals. If user_id is provided, returns only matchups for that user; otherwise returns all matchups in the league.';
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
                    'type' => 'string',
                    'description' => 'Optional Sleeper user ID to filter matchups to only show this user\'s matchups',
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
            'title' => 'Fetch Enriched League Matchups',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => true, // Makes API calls to Sleeper and database queries

            // Custom annotations
            'category' => 'fantasy-sports',
            'data_source' => 'mixed', // External API + local database
            'cache_recommended' => true,
            'notes' => 'Returns fully enriched matchup data including player projections, win probabilities, and confidence intervals. Each matchup contains two teams with detailed player and roster information.',
        ];
    }

    public function execute(array $arguments): mixed
    {
        $league_id = $arguments['league_id'] ?? null;
        $week = $arguments['week'] ?? null;
        $user_id = $arguments['user_id'] ?? null;
        $sport = $arguments['sport'] ?? 'nfl';

        if ($week === null) {
            $week = new GetSeasonState($sport)->execute()['week'];
        }

        // Get basic matchup data with owners
        $matchups = new GetMatchupsWithOwners()->execute($league_id, $week);

        if (empty($matchups)) {
            return [
                'success' => true,
                'data' => [
                    'league_id' => $league_id,
                    'week' => (int) $week,
                    'matchups' => [],
                ],
                'count' => 0,
                'message' => 'No matchups available for week '.(int) $week,
                'metadata' => [
                    'sport' => $sport,
                ],
            ];
        }

        // Filter by user if specified
        if ($user_id !== null) {
            $matchups = new FilterMatchups()->execute($matchups, $user_id);
        }

        // Get current season for player data enrichment
        $seasonState = new GetSeasonState($sport)->execute();
        $season = $seasonState['season'];

        // Enrich with player data, projections, and calculate win probabilities
        $matchups = new EnrichMatchupsWithPlayerData()->execute($matchups, $season, $week);

        // Merge with roster positions
        $matchups = new MergeEnrichedMatchupsWithRosterPositions()->execute($matchups, $league_id);

        return [
            'success' => true,
            'data' => [
                'league_id' => $league_id,
                'week' => (int) $week,
                'matchups' => $matchups,
            ],
            'count' => count($matchups),
            'message' => 'Fetched '.count($matchups).' enriched matchups for week '.(int) $week.($user_id ? ' (filtered for user '.$user_id.')' : ''),
            'metadata' => [
                'sport' => $sport,
                'season' => $season,
                'user_filtered' => $user_id !== null,
            ],
        ];
    }
}
