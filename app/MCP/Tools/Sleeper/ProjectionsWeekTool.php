<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class ProjectionsWeekTool implements ToolInterface
{
    public function name(): string
    {
        return 'projections.week';
    }

    public function description(): string
    {
        return 'Get weekly projections for a season/week (raw Sleeper output).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['season', 'week'],
            'properties' => [
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'season' => ['type' => 'string'],
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
        $sport = $arguments['sport'] ?? 'nfl';
        $season = (string) $arguments['season'];
        $week = (int) $arguments['week'];

        $projections = $sdk->getWeeklyProjections($season, $week, $sport);
        return [ 'season' => $season, 'week' => $week, 'projections' => $projections ];
    }
}
