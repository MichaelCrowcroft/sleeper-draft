<?php

namespace App\MCP\Tools;

use App\Actions\Players\AddOwnerToPlayers;
use App\Actions\Players\AvailablePositions;
use App\Actions\Players\AvailableTeams;
use App\Actions\Players\GetRosteredPlayers;
use App\Actions\Sleeper\GetSeasonState;
use App\Models\Player;
use App\Models\PlayerStats;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchPlayersTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-players';
    }

    public function description(): string
    {
        return 'Fetch a paginated, enriched list of players with comprehensive filtering and league integration. Pass a league ID and you can see free agents and rostered player owner information.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => [
                    'type' => 'string',
                    'description' => 'The search query to filter players by',
                ],
                'position' => [
                    'type' => 'string',
                    'enum' => ['QB', 'RB', 'WR', 'TE', 'K', 'DEF', 'DST'],
                    'description' => 'The position of the players to fetch',
                ],
                'team' => [
                    'type' => 'string',
                    'description' => 'The team of the players to fetch',
                ],
                'league_id' => [
                    'type' => 'string',
                    'description' => 'The league ID to fetch players from',
                ],
                'fa_only' => [
                    'type' => 'boolean',
                    'description' => 'Whether to fetch only free agents (only works if league_id is provided)',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'description' => 'The number of players to fetch per page',
                ],
                'page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'The page number to fetch',
                ],
            ],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Fetch Players Table',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
            'category' => 'fantasy-sports',
            'data_source' => 'database',
            'cache_recommended' => true,
        ];
    }

    public function execute(array $arguments): mixed
    {
        $perPage = (int) ($arguments['per_page'] ?? 10);

        if(isset($arguments['page'])) {
            request()->merge(['page' => (int) $arguments['page']]);
        }

        $rostered_players = new GetRosteredPlayers()->execute($arguments['league_id'] ?? '');
        $excluded_player_ids = ($arguments['fa_only'] ?? false) ? array_keys($rostered_players) : [];
        $state = new GetSeasonState()->execute('nfl');

        $players = Player::query()
            ->where('active', true)
            ->when($arguments['position'] ?? null, fn ($q) => $q->where('position', $arguments['position']))
            ->when($arguments['team'] ?? null, fn ($q) => $q->where('team', $arguments['team']))
            ->when($arguments['search'] ?? null, fn ($q) => $q->search($arguments['search']))
            ->whereNotIn('player_id', $excluded_player_ids)
            ->select([
                'players.player_id',
                'players.full_name',
                'players.position',
                'players.team',
                'players.active',
                'players.age',
                'players.years_exp',
                'players.number',
                'players.height',
                'players.weight',
                'players.injury_status',
                'players.injury_start_date',
                'players.news_updated',
                'players.adp',
                'players.adp_formatted',
                'players.adds_24h',
                'players.drops_24h',
                'players.times_drafted',
                'players.bye_week'
            ])
            ->selectSub(PlayerStats::query()
                ->select('weekly_ranking')
                ->whereColumn('player_stats.player_id', 'players.player_id')
                ->where('season', $state['season'])
                ->where('week', $state['week'])
                ->limit(1),
                'weekly_position_rank'
            )->playablePositions()
            ->orderByAdp()
            ->with([
                'seasonSummaries',
                'projections' => fn ($query) => $query
                    ->where('season', $state['season'])
                    ->where('week', $state['week'])
            ])
            ->paginate($perPage);

        $players = new AddOwnerToPlayers()->execute($players, $rostered_players);

        return [
            'success' => true,
            'data' => $players,
            'count' => count($players),
            'message' => 'Fetched '.count($players).' players with comprehensive data',
            'metadata' => [
                'executed_at' => now()->toISOString(),
                'filters' => [
                    'search' => $arguments['search'] ?? null,
                    'position' => $arguments['position'] ?? null,
                    'team' => $arguments['team'] ?? null,
                    'league_id' => $arguments['league_id'] ?? null,
                    'fa_only' => (bool) ($arguments['fa_only'] ?? false),
                ],
                'filter_options' => [
                    'available_positions' => new AvailablePositions()->execute(),
                    'available_teams' => new AvailableTeams()->execute(),
                ],
                'current_week' => $state['week'] ?? null,
                'current_season' => $state['season'] ?? null,
                'pagination' => [
                    'current_page' => $players->currentPage(),
                    'per_page' => $players->perPage(),
                    'total' => $players->total(),
                    'last_page' => $players->lastPage(),
                    'has_more' => $players->hasMorePages(),
                ],
            ],
        ];
    }
}
