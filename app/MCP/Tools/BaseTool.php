<?php

namespace App\MCP\Tools;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use Illuminate\Support\Facades\Cache;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

abstract class BaseTool implements ToolInterface
{
    protected function getContextDefaults(): array
    {
        // Note: single global key for now; multi-tenant isolation can be added later
        return Cache::get('mcp:defaults', []);
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
}
