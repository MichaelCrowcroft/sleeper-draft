<?php

namespace App\MCP\Tools\Data;

use App\Services\SleeperSdk;
use App\Support\PlayerInfo;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class UnifiedDataTool implements ToolInterface
{
    public function name(): string
    {
        return 'fantasy_data';
    }

    public function description(): string
    {
        return 'Unified tool for accessing fantasy data: leagues, rosters, drafts, and players.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['data_type'],
            'properties' => [
                'data_type' => [
                    'type' => 'string',
                    'enum' => ['league', 'rosters', 'drafts', 'draft_picks', 'players', 'transactions'],
                    'description' => 'Type of data to retrieve',
                ],

                // Common parameters
                'sport' => ['type' => 'string', 'default' => 'nfl'],

                // League and roster parameters
                'league_id' => ['type' => 'string', 'description' => 'Required for league, rosters, drafts, and transactions data types'],

                // Draft parameters
                'draft_id' => ['type' => 'string', 'description' => 'Required for draft_picks data type'],

                // Transaction parameters
                'week' => ['type' => 'integer', 'description' => 'Optional week filter for transactions data type'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'default' => 1000, 'description' => 'Limit results for draft_picks'],

                // Player search parameters
                'query' => ['type' => 'string', 'description' => 'Required for players data type'],
                'position' => ['type' => 'string', 'description' => 'Optional filter for players data type'],
                'team' => ['type' => 'string', 'description' => 'Optional filter for players data type'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function annotations(): array
    {
        return [];
    }

    public function execute(array $arguments): mixed
    {
        $dataType = $arguments['data_type'];

        return match ($dataType) {
            'league' => $this->getLeagueData($arguments),
            'rosters' => $this->getRostersData($arguments),
            'drafts' => $this->getDraftsData($arguments),
            'draft_picks' => $this->getDraftPicksData($arguments),
            'players' => $this->searchPlayers($arguments),
            'transactions' => $this->getTransactionsData($arguments),
            default => ['error' => 'Invalid data type specified']
        };
    }

    private function getLeagueData(array $arguments): array
    {
        if (! isset($arguments['league_id'])) {
            throw new \InvalidArgumentException('Missing required parameter: league_id');
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $leagueId = (string) $arguments['league_id'];

        $league = $sdk->getLeague($leagueId);

        return [
            'data_type' => 'league',
            'league' => $league,
            'league_id' => $leagueId,
        ];
    }

    private function getRostersData(array $arguments): array
    {
        if (! isset($arguments['league_id'])) {
            throw new \InvalidArgumentException('Missing required parameter: league_id');
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $leagueId = (string) $arguments['league_id'];
        $sport = $arguments['sport'] ?? 'nfl';

        $rosters = $sdk->getLeagueRosters($leagueId);
        $ids = [];
        foreach ($rosters as $roster) {
            foreach ((array) ($roster['players'] ?? []) as $pid) {
                $ids[] = (string) $pid;
            }
        }
        $players = PlayerInfo::fetch($ids, $sport);

        return [
            'data_type' => 'rosters',
            'rosters' => $rosters,
            'league_id' => $leagueId,
            'count' => count($rosters),
            'players' => $players,
        ];
    }

    private function getDraftsData(array $arguments): array
    {
        if (! isset($arguments['league_id'])) {
            throw new \InvalidArgumentException('Missing required parameter: league_id');
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $leagueId = (string) $arguments['league_id'];

        $drafts = $sdk->getLeagueDrafts($leagueId);

        return [
            'data_type' => 'drafts',
            'drafts' => $drafts,
            'league_id' => $leagueId,
            'count' => count($drafts),
        ];
    }

    private function getDraftPicksData(array $arguments): array
    {
        if (! isset($arguments['draft_id'])) {
            throw new \InvalidArgumentException('Missing required parameter: draft_id');
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $draftId = (string) $arguments['draft_id'];
        $limit = (int) ($arguments['limit'] ?? 1000);
        $sport = $arguments['sport'] ?? 'nfl';

        $picks = $sdk->getDraftPicks($draftId);
        $limitedPicks = array_slice($picks, 0, $limit);
        $ids = [];
        foreach ($limitedPicks as $pick) {
            if (! empty($pick['player_id'])) {
                $ids[] = (string) $pick['player_id'];
            }
        }
        $players = PlayerInfo::fetch($ids, $sport);

        return [
            'data_type' => 'draft_picks',
            'picks' => $limitedPicks,
            'draft_id' => $draftId,
            'count' => count($limitedPicks),
            'limit' => $limit,
            'players' => $players,
        ];
    }

    private function searchPlayers(array $arguments): array
    {
        if (! isset($arguments['query'])) {
            throw new \InvalidArgumentException('Missing required parameter: query');
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $sport = $arguments['sport'] ?? 'nfl';
        // Trim the query to avoid mismatches caused by leading/trailing spaces
        $query = trim((string) $arguments['query']);
        $position = $arguments['position'] ?? null;
        $team = $arguments['team'] ?? null;

        $catalog = $sdk->getPlayersCatalog($sport);
        $results = [];

        foreach ($catalog as $pid => $meta) {
            $fullName = $meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? ''));

            // Check if name matches query
            if (stripos($fullName, $query) === false) {
                continue;
            }

            // Apply filters
            if ($position && strtoupper((string) $meta['position']) !== strtoupper($position)) {
                continue;
            }

            if ($team && strtoupper((string) $meta['team']) !== strtoupper($team)) {
                continue;
            }

            $results[] = [
                'player_id' => (string) ($meta['player_id'] ?? $pid),
                'name' => $fullName,
                'position' => $meta['position'] ?? null,
                'team' => $meta['team'] ?? null,
                'age' => $meta['age'] ?? null,
                'height' => $meta['height'] ?? null,
                'weight' => $meta['weight'] ?? null,
            ];

            // Limit results to prevent overwhelming response
            if (count($results) >= 20) {
                break;
            }
        }

        return [
            'data_type' => 'players',
            'query' => $query,
            'results' => $results,
            'count' => count($results),
            'filters' => [
                'position' => $position,
                'team' => $team,
            ],
        ];
    }

    private function getTransactionsData(array $arguments): array
    {
        if (! isset($arguments['league_id'])) {
            throw new \InvalidArgumentException('Missing required parameter: league_id');
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $leagueId = (string) $arguments['league_id'];
        $week = $arguments['week'] ?? null;
        $sport = $arguments['sport'] ?? 'nfl';

        $transactions = $sdk->getLeagueTransactions($leagueId, $week);
        $ids = [];
        foreach ($transactions as $tx) {
            if (isset($tx['adds']) && is_array($tx['adds'])) {
                $ids = array_merge($ids, array_keys($tx['adds']));
            }
            if (isset($tx['drops']) && is_array($tx['drops'])) {
                $ids = array_merge($ids, array_keys($tx['drops']));
            }
        }
        $players = PlayerInfo::fetch($ids, $sport);

        return [
            'data_type' => 'transactions',
            'transactions' => $transactions,
            'league_id' => $leagueId,
            'week' => $week,
            'count' => count($transactions),
            'players' => $players,
        ];
    }
}
