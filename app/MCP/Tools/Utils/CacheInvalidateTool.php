<?php

namespace App\MCP\Tools\Utils;

use Illuminate\Support\Facades\Cache;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class CacheInvalidateTool implements ToolInterface
{
    public function name(): string
    {
        return 'cache_invalidate';
    }

    public function description(): string
    {
        return 'Invalidate cached keys by scope.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['scope'],
            'properties' => [
                'scope' => ['type' => 'string', 'enum' => ['user','league','season','all']],
                'id' => ['type' => 'string'],
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
        $scope = $arguments['scope'];
        $id = $arguments['id'] ?? '';
        $prefix = match ($scope) {
            'user' => 'sleeper:user:'.$id,
            'league' => 'sleeper:league:'.$id,
            'season' => 'sleeper:projections:nfl:'.$id, // simplistic
            'all' => 'sleeper:',
            default => 'sleeper:',
        };

        // Laravel cache does not support prefix clear natively; no-op here except for full clear via flush
        $cleared = 0;
        if ($scope === 'all') {
            Cache::flush();
            $cleared = -1;
        }

        return ['cleared_keys' => $cleared, 'note' => $scope === 'all' ? 'cache flushed' : 'prefix clearing not supported'];
    }
}
