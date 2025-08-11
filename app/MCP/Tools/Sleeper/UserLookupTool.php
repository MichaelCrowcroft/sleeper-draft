<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class UserLookupTool implements ToolInterface
{
    public function name(): string
    {
        return 'user_lookup';
    }

    public function description(): string
    {
        return 'Get Sleeper user by username and return user_id and profile info.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['username'],
            'properties' => [
                'username' => ['type' => 'string', 'minLength' => 1],
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
        $user = $sdk->getUserByUsername($arguments['username']);

        return ['user' => $user];
    }
}
