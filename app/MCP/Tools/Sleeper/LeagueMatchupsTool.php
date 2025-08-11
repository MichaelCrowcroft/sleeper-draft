<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class LeagueMatchupsTool implements ToolInterface
{
    public function name(): string
    {
        return 'league_matchups';
    }

    public function description(): string
    {
        return 'Get weekly matchups for a Sleeper league.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['league_id'],
            'properties' => [
                'league_id' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
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
        $week = (int) ($arguments['week'] ?? 1);
        $matchups = $sdk->getLeagueMatchups($arguments['league_id'], $week);

        return [ 'week' => $week, 'matchups' => $matchups ];
    }
}
