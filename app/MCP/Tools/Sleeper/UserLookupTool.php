<?php

namespace App\MCP\Tools\Sleeper;

use App\MCP\Tools\BaseTool;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;

class UserLookupTool extends BaseTool
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
        // Validate required parameters
        $this->validateRequired($arguments, ['username']);

        $username = $this->getParam($arguments, 'username', '', true);

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);

        try {
            $user = $sdk->getUserByUsername($username);

            if (empty($user)) {
                return [
                    'success' => false,
                    'error' => 'User not found',
                    'username' => $username,
                    'user' => null,
                ];
            }

            return [
                'success' => true,
                'user' => $user,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve user: '.$e->getMessage(),
                'username' => $username,
                'user' => null,
            ];
        }
    }
}
