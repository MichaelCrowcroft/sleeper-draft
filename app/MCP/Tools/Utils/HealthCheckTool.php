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
        return 'Verify MCP server and Sleeper reachability.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false];
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
        $latency = (int) ((microtime(true) - $start) * 1000);

        return [
            'server_ok' => $serverOk,
            'sleeper_ok' => $sleeperOk,
            'latency_ms' => $latency,
        ];
    }
}
