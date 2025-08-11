<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;

class UserLeaguesTool implements ToolInterface
{
    public function name(): string
    {
        return 'user.leagues';
    }

    public function description(): string
    {
        return 'List Sleeper leagues for a user in a season. Use user.lookup tool first to get user_id from username.';
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
        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);

        $userIdRaw = $arguments['user_id'] ?? null;
        if (! is_string($userIdRaw) || trim($userIdRaw) === '') {
            throw new JsonRpcErrorException(message: 'Missing required parameter: user_id', code: JsonRpcErrorCode::INVALID_PARAMS);
        }
        $userId = $userIdRaw;
        $sport = $arguments['sport'] ?? 'nfl';
        $season = $arguments['season'] ?? date('Y');

        $leagues = $sdk->getUserLeagues($userId, $sport, $season);

        return [
            'user_id' => $userId,
            'season' => $season,
            'sport' => $sport,
            'leagues' => $leagues,
        ];
    }
}
