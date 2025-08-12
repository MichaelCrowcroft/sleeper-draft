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
        $tags = ['sleeper'];
        if ($scope === 'user' && $id !== '') {
            $tags[] = 'user:'.$id;
        } elseif ($scope === 'league' && $id !== '') {
            $tags[] = 'league:'.$id;
        } elseif ($scope === 'season' && $id !== '') {
            $tags[] = 'season:'.$id;
        }

        $supportsTags = method_exists(Cache::store(), 'tags');
        if ($scope === 'all') {
            Cache::flush();
            return ['cleared_keys' => -1, 'note' => 'cache flushed'];
        }

        if ($supportsTags && count($tags) > 1) {
            Cache::tags($tags)->flush();
            return ['cleared_keys' => -1, 'note' => 'flushed tagged cache: '.implode(',', $tags)];
        }

        // Fallback: no-op when tags unsupported
        return ['cleared_keys' => 0, 'note' => 'tagged cache not supported by current driver'];
    }
}
