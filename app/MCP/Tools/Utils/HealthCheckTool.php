<?php

namespace App\MCP\Tools\Utils;

use Illuminate\Support\Facades\Http;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class HealthCheckTool implements ToolInterface
{
    public function name(): string
    {
        return 'health_check';
    }

    public function description(): string
    {
        return 'Verify MCP server, Sleeper, and ESPN reachability.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                '_dummy' => ['type' => 'string', 'description' => 'Unused parameter for Anthropic compatibility'],
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
        $serverOk = true;
        $start = microtime(true);
        try {
            $resp = Http::timeout(5)->get('https://api.sleeper.app/v1/state/nfl');
            $sleeperOk = $resp->successful();
        } catch (\Throwable $e) {
            $sleeperOk = false;
        }
        // ESPN core athletes quick check
        try {
            $espnCore = Http::timeout(5)->get('https://sports.core.api.espn.com/v3/sports/football/nfl/athletes?page=1&limit=1');
            $espnCoreOk = $espnCore->successful();
        } catch (\Throwable $e) {
            $espnCoreOk = false;
        }
        // ESPN fantasy quick check (minimal page)
        try {
            $espnFantasy = Http::timeout(5)->get('https://lm-api-reads.fantasy.espn.com/apis/v3/games/ffl/seasons/'.date('Y').'/players?view=mDraftDetail&limit=1');
            $espnFantasyOk = $espnFantasy->successful();
        } catch (\Throwable $e) {
            $espnFantasyOk = false;
        }
        $latency = (int) ((microtime(true) - $start) * 1000);

        return [
            'server_ok' => $serverOk,
            'sleeper_ok' => $sleeperOk,
            'espn_core_ok' => $espnCoreOk,
            'espn_fantasy_ok' => $espnFantasyOk,
            'latency_ms' => $latency,
        ];
    }
}
