<?php

namespace App\MCP\Tools\Utils;

use Illuminate\Support\Facades\Cache;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class ContextSetDefaultsTool implements ToolInterface
{
    public function name(): string
    {
        return 'context.set_defaults';
    }

    public function description(): string
    {
        return 'Set default username/user_id, league_id, sport, season, week for subsequent calls.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'username' => ['type' => 'string'],
                'user_id' => ['type' => 'string'],
                'league_id' => ['type' => 'string'],
                'sport' => ['type' => 'string'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer'],
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
        // For simplicity, use a single cache key; in production tie to auth/session/client-id
        $key = 'mcp:defaults';
        $current = Cache::get($key, []);
        $next = array_filter(array_merge($current, $arguments), fn ($v) => $v !== null && $v !== '');
        Cache::put($key, $next, now()->addDays(7));
        return ['success' => true, 'context' => $next];
    }
}
