<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class UserLeaguesTool implements ToolInterface
{
    public function name(): string
    {
        return 'user.leagues';
    }

    public function description(): string
    {
        return 'List Sleeper leagues for a user in a season. Requires either user_id or username.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'user_id' => ['type' => 'string'],
                'username' => ['type' => 'string'],
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
        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);

        $sport = $arguments['sport'] ?? 'nfl';
        $season = $arguments['season'] ?? date('Y');

        // Validate that either user_id or username is provided
        if (empty($arguments['user_id']) && empty($arguments['username'])) {
            throw new \OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException(
                message: 'Either user_id or username must be provided',
                code: \OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        if (! empty($arguments['username'])) {
            $user = $sdk->getUserByUsername($arguments['username']);
            $userId = (string) $user['user_id'];
        } else {
            $userId = (string) $arguments['user_id'];
        }

        $leagues = $sdk->getUserLeagues($userId, $sport, $season);

        return [
            'user_id' => $userId,
            'season' => $season,
            'sport' => $sport,
            'leagues' => $leagues,
        ];
    }
}
