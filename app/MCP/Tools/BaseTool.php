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
