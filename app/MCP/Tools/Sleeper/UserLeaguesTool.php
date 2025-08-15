<?php

namespace App\MCP\Tools\Sleeper;

use App\MCP\Tools\BaseTool;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;

class UserLeaguesTool extends BaseTool
{
    public function name(): string
    {
        return 'user_leagues';
    }

    public function description(): string
    {
        return 'List Sleeper leagues for a user in a season. Use user_lookup tool first to get user_id from username.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id'],
            'properties' => [
                'user_id' => ['type' => 'string'],
                'season' => ['type' => 'string', 'default' => date('Y')],
                'sport' => ['type' => 'string', 'enum' => ['nfl', 'nba', 'mlb', 'nhl'], 'default' => 'nfl'],
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
        // Validate required parameters
        $this->validateRequired($arguments, ['user_id']);

        $userId = $this->getParam($arguments, 'user_id', '', true);
        $sport = $this->getParam($arguments, 'sport', 'nfl');
        $season = $this->getParam($arguments, 'season', date('Y'));

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);

        try {
            $leagues = $sdk->getUserLeagues($userId, $sport, $season);

            return [
                'success' => true,
                'user_id' => $userId,
                'season' => $season,
                'sport' => $sport,
                'leagues' => $leagues ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve user leagues: '.$e->getMessage(),
                'user_id' => $userId,
                'season' => $season,
                'sport' => $sport,
                'leagues' => [],
            ];
        }
    }
}
