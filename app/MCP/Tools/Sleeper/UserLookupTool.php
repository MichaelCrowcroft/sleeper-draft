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
            'required' => [], // Username is now optional when authenticated
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'description' => 'Sleeper username to look up. If not provided and user is authenticated, uses the authenticated user\'s sleeper username.',
                ],
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
            // Get username - either from authenticated user or parameters
            $username = $this->getSleeperUsername($arguments);

            /** @var SleeperSdk $sdk */
            $sdk = LaravelApp::make(SleeperSdk::class);

            $user = $sdk->getUserByUsername($username);

            if (empty($user)) {
                return [
                    'success' => false,
                    'error' => 'User not found',
                    'username' => $username,
                    'user' => null,
                    'authenticated' => $this->isAuthenticated(),
                ];
            }

            return [
                'success' => true,
                'user' => $user,
                'authenticated' => $this->isAuthenticated(),
                'source' => $this->isAuthenticated() ? 'authenticated_user' : 'provided_username',
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'authenticated' => $this->isAuthenticated(),
                'user' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve user: '.$e->getMessage(),
                'authenticated' => $this->isAuthenticated(),
                'user' => null,
            ];
        }
    }
}
