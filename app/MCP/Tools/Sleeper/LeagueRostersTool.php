<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class LeagueRostersTool implements ToolInterface
{
    public function name(): string
    {
        return 'league_rosters';
    }

    public function description(): string
    {
        return 'Get all rosters for a Sleeper league.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['league_id'],
            'properties' => [
                'league_id' => ['type' => 'string'],
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
        $rosters = $sdk->getLeagueRosters($arguments['league_id']);

        return ['rosters' => $rosters];
    }
}
