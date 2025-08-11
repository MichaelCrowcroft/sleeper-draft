<?php

namespace App\MCP\Tools\Preferences;

use Illuminate\Support\Facades\Cache;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class StrategySetTool implements ToolInterface
{
    public function name(): string
    {
        return 'strategy_set';
    }

    public function description(): string
    {
        return 'Configure draft/season strategy levers (risk tolerance, stacking, exposure caps).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'risk' => ['type' => 'string', 'enum' => ['low','medium','high']],
                'stack_qb' => ['type' => 'boolean'],
                'hero_rb' => ['type' => 'boolean'],
                'zero_rb' => ['type' => 'boolean'],
                'exposure_caps' => ['type' => 'object'],
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
        $key = 'mcp:strategy';
        $current = Cache::get($key, []);
        $next = array_filter(array_merge($current, $arguments), fn ($v) => $v !== null);
        Cache::put($key, $next, now()->addDays(30));
        return ['success' => true, 'strategy' => $next];
    }
}
