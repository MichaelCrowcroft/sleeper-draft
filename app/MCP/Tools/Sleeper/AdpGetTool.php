<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class AdpGetTool implements ToolInterface
{
    public function name(): string
    {
        return 'adp.get';
    }

    public function description(): string
    {
        return 'Get current ADP/market values.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['season'],
            'properties' => [
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'season' => ['type' => 'string'],
                'format' => ['type' => 'string', 'enum' => ['redraft','dynasty','bestball'], 'default' => 'redraft'],
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
        $format = $arguments['format'] ?? 'redraft';

        $adp = $sdk->getAdp($season, $format, $sport);
        return [ 'season' => $season, 'format' => $format, 'adp' => $adp ];
    }
}
