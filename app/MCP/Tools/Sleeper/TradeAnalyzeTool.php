<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class TradeAnalyzeTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false; // simple HTTP analysis for now
    }

    public function name(): string
    {
        return 'trade_analyze';
    }

    public function description(): string
    {
        return 'Evaluate a proposed trade with simple value proxy via ADP and projections.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['league_id', 'season', 'week', 'offer'],
            'properties' => [
                'league_id' => ['type' => 'string'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'offer' => [
                    'type' => 'object',
                    'required' => ['from_roster_id','to_roster_id','sending','receiving'],
                    'properties' => [
                        'from_roster_id' => ['type' => 'integer'],
                        'to_roster_id' => ['type' => 'integer'],
                        'sending' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'receiving' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'additionalProperties' => false,
                ],
                'format' => ['type' => 'string', 'enum' => ['redraft','dynasty','bestball'], 'default' => 'redraft'],
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
        $sport = $arguments['sport'] ?? 'nfl';
        $leagueId = (string) $arguments['league_id'];
        $season = (string) $arguments['season'];
        $week = (int) $arguments['week'];
        $format = $arguments['format'] ?? 'redraft';
        $offer = $arguments['offer'];

        // Load context
        $league = $sdk->getLeague($leagueId);
        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);
        $adp = $sdk->getAdp($season, $format, $sport);
        $adpIndex = [];
        foreach ($adp as $row) {
            $adpIndex[(string) ($row['player_id'] ?? '')] = (float) ($row['adp'] ?? 999.0);
        }

        $value = function (array $playerIds): float {
            return 0.0;
        };

        $value = function (array $playerIds) use ($projections, $adpIndex): float {
            $sum = 0.0;
            foreach ($playerIds as $pid) {
                $pid = (string) $pid;
                $proj = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
                $adpVal = $adpIndex[$pid] ?? 999.0;
                $marketScore = $adpVal > 0 ? (200.0 - min($adpVal, 200.0)) : 0.0; // higher for better ADP
                $sum += $proj + $marketScore / 10.0;
            }
            return $sum;
        };

        $sendingValue = $value($offer['sending'] ?? []);
        $receivingValue = $value($offer['receiving'] ?? []);

        $fair = abs($sendingValue - $receivingValue) <= max(2.0, 0.1 * max($sendingValue, $receivingValue));
        $recommendation = $receivingValue >= $sendingValue ? 'accept' : 'decline';

        return [
            'from_roster_id' => (int) $offer['from_roster_id'],
            'to_roster_id' => (int) $offer['to_roster_id'],
            'sending_value' => $sendingValue,
            'receiving_value' => $receivingValue,
            'fair' => $fair,
            'recommendation' => $recommendation,
        ];
    }
}
