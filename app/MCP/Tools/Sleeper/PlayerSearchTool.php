<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class PlayerSearchTool implements ToolInterface
{
    public function name(): string
    {
        return 'players_search';
    }

    public function description(): string
    {
        return 'Search players by name, team, or position using the Sleeper players catalog.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                'query' => ['type' => 'string', 'minLength' => 1],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'position' => ['type' => 'string'],
                'team' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'default' => 25],
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
        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $sport = $arguments['sport'] ?? 'nfl';
        $catalog = $sdk->getPlayersCatalog($sport);

        $query = strtolower($arguments['query']);
        $position = isset($arguments['position']) ? strtolower($arguments['position']) : null;
        $team = isset($arguments['team']) ? strtolower($arguments['team']) : null;
        $limit = (int) ($arguments['limit'] ?? 25);

        $results = [];
        foreach ($catalog as $playerId => $p) {
            $name = strtolower(($p['full_name'] ?? $p['first_name'].' '.$p['last_name']) ?? '');
            $pos = strtolower($p['position'] ?? '');
            $tm = strtolower($p['team'] ?? '');

            if ($name === '' && ($p['player_id'] ?? '') === '') {
                continue;
            }

            if (! str_contains($name, $query)) {
                continue;
            }
            if ($position && $pos !== $position) {
                continue;
            }
            if ($team && $tm !== $team) {
                continue;
            }

            $results[] = [
                'player_id' => (string) ($p['player_id'] ?? $playerId),
                'name' => $p['full_name'] ?? trim(($p['first_name'] ?? '').' '.($p['last_name'] ?? '')),
                'team' => $p['team'] ?? null,
                'position' => $p['position'] ?? null,
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return ['players' => $results];
    }
}
