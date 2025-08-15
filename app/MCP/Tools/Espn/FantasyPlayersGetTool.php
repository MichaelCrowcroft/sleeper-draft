<?php

namespace App\MCP\Tools\Espn;

use App\MCP\Tools\BaseTool;
use App\Services\EspnSdk;
use Illuminate\Support\Facades\App as LaravelApp;

class FantasyPlayersGetTool extends BaseTool
{
    public function name(): string
    {
        return 'espn_fantasy_players_get';
    }

    public function description(): string
    {
        return 'Get fantasy players from ESPN Fantasy API (lm-api-reads.fantasy.espn.com).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['season'],
            'properties' => [
                'season' => ['type' => 'integer'],
                'view' => ['type' => 'string', 'default' => 'mDraftDetail'],
                'limit' => ['type' => 'integer'],
                'fantasy_filter' => ['type' => 'object', 'description' => 'JSON object to send as X-Fantasy-Filter header'],
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
        /** @var EspnSdk $sdk */
        $sdk = LaravelApp::make(EspnSdk::class);
        // Inline validation to avoid dependency on BaseTool helpers in some runtimes
        if (! isset($arguments['season']) || $arguments['season'] === null || $arguments['season'] === '') {
            throw new \InvalidArgumentException('Missing required parameter: season');
        }
        $season = (int) $arguments['season'];
        $view = (string) ($arguments['view'] ?? 'mDraftDetail');
        $limit = isset($arguments['limit']) ? (int) $arguments['limit'] : null;
        $filter = isset($arguments['fantasy_filter']) && is_array($arguments['fantasy_filter']) ? $arguments['fantasy_filter'] : null;

        $data = $sdk->getFantasyPlayers($season, $view, $limit, $filter);

        return ['season' => $season, 'view' => $view, 'limit' => $limit, 'players' => $data];
    }

    // Provide local fallbacks in case parent BaseTool helpers are not available at runtime
    protected function validateRequired(array $arguments, array $required): void
    {
        foreach ($required as $field) {
            if (! isset($arguments[$field]) || $arguments[$field] === null || $arguments[$field] === '') {
                throw new \InvalidArgumentException("Missing required parameter: {$field}");
            }
        }
    }

    protected function getParam(array $arguments, string $key, mixed $default = null, bool $required = false): mixed
    {
        if ($required && (! isset($arguments[$key]) || $arguments[$key] === null || $arguments[$key] === '')) {
            throw new \InvalidArgumentException("Missing required parameter: {$key}");
        }

        return $arguments[$key] ?? $default;
    }
}
