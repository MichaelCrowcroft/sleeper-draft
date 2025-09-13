<?php

namespace App\MCP\Tools;

use App\Actions\Players\AddOwnerToPlayers;
use App\Actions\Players\GetRosteredPlayers;
use App\Actions\Sleeper\GetSeasonState;
use App\Models\Player;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchTrendingPlayersTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-trending-players';
    }

    public function description(): string
    {
        return 'Fetches trending players from the database based on adds or drops within the last 24 hours. Returns players ordered by trending value in descending order.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'position' => [
                    'type' => 'string',
                    'enum' => ['QB', 'RB', 'WR', 'TE', 'K', 'DEF', 'DST'],
                    'description' => 'The position of the players to fetch',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['add', 'drop'],
                    'description' => 'The type of trending data to fetch: "add" for players being added, "drop" for players being dropped',
                ],
                'league_id' => [
                    'type' => 'integer',
                    'description' => 'The league ID to fetch players from',
                ],
                'fa_only' => [
                    'type' => 'boolean',
                    'description' => 'Whether to fetch only free agents (only works if league_id is provided)',
                ],
            ],
            'required' => ['type'],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Fetch Trending Players',
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
        $type = $arguments['type'];
        $column = $type === 'add' ? 'adds_24h' : 'drops_24h';
        $league_id = (int) $arguments['league_id'] ?? '';
        $fa_only = (bool) $arguments['fa_only'] ?? false;
        $position = $arguments['position'] ?? null;

        $rostered_players = new GetRosteredPlayers()->execute($league_id);
        $excluded_player_ids = $fa_only ? array_keys($rostered_players) : [];
        $state = new GetSeasonState()->execute('nfl');

        $players = Player::query()
            ->where('active', true)
            ->when($position, fn ($q) => $q->where('position', $position))
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
            ->whereNotNull($column)
            ->orderBy($column, 'desc')
            ->limit(10)
            ->get();

        $players = new AddOwnerToPlayers()->execute($players, $rostered_players);

        return [
            'success' => true,
            'data' => $players,
            'count' => $players->count(),
            'message' => "Successfully fetched {$players->count()} trending players for type '{$type}'",
            'metadata' => [
                'type' => $type,
                'column' => $column,
                'executed_at' => now()->toISOString(),
                'filters' => [
                    'league_id' => $league_id,
                    'fa_only' => $fa_only,
                    'position' => $position,
                ],
                'current_week' => $state['week'],
                'current_season' => $state['season'],
            ],
        ];
    }
}
