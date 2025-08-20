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
        return 'List Sleeper leagues for a user in a season. If authenticated, uses the authenticated user\'s sleeper account. Otherwise, requires user_id parameter.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [], // user_id is optional when authenticated
            'properties' => [
                'user_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper user ID. If not provided and user is authenticated, uses the authenticated user\'s sleeper user ID.',
                ],
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
        try {
            // Get user ID - either from authenticated user or parameters
            $userId = $this->getSleeperUserId($arguments);
            $sport = $this->getParam($arguments, 'sport', 'nfl');
            $season = $this->getParam($arguments, 'season', date('Y'));

            /** @var SleeperSdk $sdk */
            $sdk = LaravelApp::make(SleeperSdk::class);

            $leagues = $sdk->getUserLeagues($userId, $sport, $season);

            return [
                'success' => true,
                'user_id' => $userId,
                'season' => $season,
                'sport' => $sport,
                'leagues' => $leagues ?? [],
                'authenticated' => $this->isAuthenticated(),
                'source' => $this->isAuthenticated() ? 'authenticated_user' : 'provided_user_id',
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'authenticated' => $this->isAuthenticated(),
                'leagues' => [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve user leagues: '.$e->getMessage(),
                'authenticated' => $this->isAuthenticated(),
                'leagues' => [],
            ];
        }
    }
}
