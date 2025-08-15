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
        $this->validateRequired($arguments, ['season']);
        /** @var EspnSdk $sdk */
        $sdk = LaravelApp::make(EspnSdk::class);
        $season = (int) $arguments['season'];
        $view = (string) ($arguments['view'] ?? 'mDraftDetail');
        $limit = isset($arguments['limit']) ? (int) $arguments['limit'] : null;
        $filter = isset($arguments['fantasy_filter']) && is_array($arguments['fantasy_filter']) ? $arguments['fantasy_filter'] : null;

        $data = $sdk->getFantasyPlayers($season, $view, $limit, $filter);

        return ['season' => $season, 'view' => $view, 'limit' => $limit, 'players' => $data];
    }
}
