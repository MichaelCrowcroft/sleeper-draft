<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class LeagueTransactionsTool implements ToolInterface
{
    public function name(): string
    {
        return 'league_transactions';
    }

    public function description(): string
    {
        return 'Get league transactions for a given week.';
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
        $state = $sdk->getState('nfl');
        $week = (int) ($arguments['week'] ?? (int) ($state['week'] ?? 1));
        $transactions = $sdk->getLeagueTransactions($arguments['league_id'], $week);

        return ['transactions' => $transactions];
    }
}
