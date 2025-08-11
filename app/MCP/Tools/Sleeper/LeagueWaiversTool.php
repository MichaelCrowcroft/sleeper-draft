<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class LeagueWaiversTool implements ToolInterface
{
    public function name(): string
    {
        return 'league.waivers';
    }

    public function description(): string
    {
        return 'Alias for league.transactions of type waivers for a given week.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['league_id', 'week'],
            'properties' => [
                'league_id' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
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
        $week = (int) $arguments['week'];
        $transactions = $sdk->getLeagueTransactions($arguments['league_id'], $week);

        $waivers = array_values(array_filter($transactions, function ($tx) {
            return ($tx['type'] ?? '') === 'waiver' || ($tx['type'] ?? '') === 'free_agent';
        }));

        return [ 'waivers' => $waivers ];
    }
}
