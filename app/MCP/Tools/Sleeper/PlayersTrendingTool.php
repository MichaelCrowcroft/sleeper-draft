<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class PlayersTrendingTool implements ToolInterface
{
    public function name(): string
    {
        return 'players_trending';
    }

    public function description(): string
    {
        return 'Get trending adds/drops over a lookback window.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['add', 'drop'], 'default' => 'add'],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'lookback_hours' => ['type' => 'integer', 'minimum' => 1, 'default' => 24],
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
        $type = $arguments['type'] ?? 'add';
        $sport = $arguments['sport'] ?? 'nfl';
        $lookback = (int) ($arguments['lookback_hours'] ?? 24);
        $limit = (int) ($arguments['limit'] ?? 25);

        $entries = $sdk->getPlayersTrending($type, $sport, $lookback, $limit);
        return [ 'type' => $type, 'entries' => $entries ];
    }
}
