<?php

namespace App\MCP\Tools;

use App\Models\User;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

abstract class BaseTool implements ToolInterface
{
    /**
     * Get the currently authenticated user from Sanctum token, if available
     */
    protected function getAuthenticatedUser(): ?User
    {
        return Auth::guard('sanctum')->user();
    }

    /**
     * Check if the current request is authenticated via Sanctum token
     */
    protected function isAuthenticated(): bool
    {
        return $this->getAuthenticatedUser() !== null;
    }

    /**
     * Get user-specific context that includes authenticated user's sleeper data
     */
    protected function getUserContext(): array
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return [];
        }

        return [
            'user_id' => $user->id,
            'sleeper_username' => $user->sleeper_username,
            'sleeper_user_id' => $user->sleeper_user_id,
        ];
    }

    /**
     * Get enhanced context defaults including user context when authenticated
     */
    protected function getContextDefaults(): array
    {
        // Note: single global key for now; multi-tenant isolation can be added later
        $defaults = Cache::get('mcp:defaults', []);

        // Add user context if authenticated
        if ($this->isAuthenticated()) {
            $defaults = array_merge($defaults, $this->getUserContext());
        }

        return $defaults;
    }

    /**
     * Resolve a value from arguments, else defaults context, else provided fallback
     */
    protected function resolveArg(array $arguments, string $key, mixed $fallback = null): mixed
    {
        $context = $this->getContextDefaults();

        return $arguments[$key] ?? $context[$key] ?? $fallback;
    }

    /**
     * Validate that required parameters are present in arguments
     *
     * @param  array  $arguments  The input arguments
     * @param  array  $required  Array of required parameter names
     *
     * @throws \InvalidArgumentException if any required parameter is missing
     */
    protected function validateRequired(array $arguments, array $required): void
    {
        foreach ($required as $field) {
            if (! isset($arguments[$field]) || $arguments[$field] === null || $arguments[$field] === '') {
                throw new \InvalidArgumentException("Missing required parameter: {$field}");
            }
        }
    }

    /**
     * Safe parameter retrieval with validation
     *
     * @param  array  $arguments  The input arguments
     * @param  string  $key  The parameter name
     * @param  mixed  $default  Default value if not present
     * @param  bool  $required  Whether the parameter is required
     * @return mixed The parameter value
     *
     * @throws \InvalidArgumentException if required parameter is missing
     */
    protected function getParam(array $arguments, string $key, mixed $default = null, bool $required = false): mixed
    {
        if ($required && (! isset($arguments[$key]) || $arguments[$key] === null || $arguments[$key] === '')) {
            throw new \InvalidArgumentException("Missing required parameter: {$key}");
        }

        return $arguments[$key] ?? $default;
    }

    /**
     * Resolve season/week: if absent, query Sleeper state for the given sport
     * Returns [season, week]
     */
    protected function resolveSeasonWeek(array $arguments, string $sport = 'nfl'): array
    {
        $season = (string) ($arguments['season'] ?? ($this->getContextDefaults()['season'] ?? ''));
        $week = $arguments['week'] ?? ($this->getContextDefaults()['week'] ?? null);

        if ($season !== '' && $week !== null) {
            return [(string) $season, (int) $week];
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $state = $sdk->getState($sport);
        $resolvedSeason = (string) ($season !== '' ? $season : ($state['season'] ?? date('Y')));
        $resolvedWeek = (int) ($week !== null ? $week : (int) ($state['week'] ?? 1));

        return [$resolvedSeason, $resolvedWeek];
    }

    /**
     * Get the sleeper username for the current context
     * If authenticated, uses the user's sleeper username
     * Otherwise, requires username parameter
     */
    protected function getSleeperUsername(array $arguments): string
    {
        $user = $this->getAuthenticatedUser();

        if ($user && $user->sleeper_username) {
            return $user->sleeper_username;
        }

        return $this->getParam($arguments, 'username', '', true);
    }

    /**
     * Get the sleeper user ID for the current context
     * If authenticated, uses the user's sleeper user ID
     * Otherwise, tries to look up the user ID from username
     */
    protected function getSleeperUserId(array $arguments): string
    {
        $user = $this->getAuthenticatedUser();

        if ($user && $user->sleeper_user_id) {
            return $user->sleeper_user_id;
        }

        $username = $this->getSleeperUsername($arguments);

        if (!$username) {
            throw new \InvalidArgumentException("Unable to determine sleeper user ID. Please provide username or authenticate with a token.");
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);

        try {
            $sleeperUser = $sdk->getUserByUsername($username);

            if (empty($sleeperUser) || !isset($sleeperUser['user_id'])) {
                throw new \InvalidArgumentException("User not found on Sleeper: {$username}");
            }

            return $sleeperUser['user_id'];
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Failed to get user ID for {$username}: " . $e->getMessage());
        }
    }
}
