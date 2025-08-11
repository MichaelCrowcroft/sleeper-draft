<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class LeagueGetTool implements ToolInterface
{
    public function name(): string
    {
        return 'league_get';
    }

    public function description(): string
    {
        return 'Get Sleeper league metadata and settings by league_id.';
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
        $league = $sdk->getLeague($arguments['league_id']);

        return [
            'league' => $league,
            'settings' => $league['settings'] ?? new \stdClass(),
        ];
    }
}
