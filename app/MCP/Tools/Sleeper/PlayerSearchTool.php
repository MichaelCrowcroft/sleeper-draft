<?php

namespace App\MCP\Tools\Sleeper;

use App\MCP\Tools\BaseTool;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;

class PlayerSearchTool extends BaseTool
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
        // Validate required parameters
        $this->validateRequired($arguments, ['query']);

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);

        $sport = $this->getParam($arguments, 'sport', 'nfl');
        $query = strtolower($this->getParam($arguments, 'query', '', true));
        $position = $this->getParam($arguments, 'position');
        $team = $this->getParam($arguments, 'team');
        $limit = (int) $this->getParam($arguments, 'limit', 25);

        if ($position) {
            $position = strtolower($position);
        }
        if ($team) {
            $team = strtolower($team);
        }

        $catalog = $sdk->getPlayersCatalog($sport);

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
