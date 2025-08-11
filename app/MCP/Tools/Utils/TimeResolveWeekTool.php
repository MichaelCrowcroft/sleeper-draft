<?php

namespace App\MCP\Tools\Utils;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class TimeResolveWeekTool implements ToolInterface
{
    public function name(): string
    {
        return 'time_resolve_week';
    }

    public function description(): string
    {
        return 'Resolve current season/week from Sleeper state.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sport' => ['type' => 'string', 'default' => 'nfl'],
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
        $state = $sdk->getState($sport);
        return [
            'season' => (string) ($state['season'] ?? date('Y')),
            'week' => (int) ($state['week'] ?? 1),
            'is_in_season' => (bool) ($state['season_type'] ?? '') === 'regular',
        ];
    }
}
