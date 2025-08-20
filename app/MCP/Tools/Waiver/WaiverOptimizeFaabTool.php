<?php

namespace App\MCP\Tools\Waiver;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class WaiverOptimizeFaabTool implements ToolInterface
{
    public function name(): string
    {
        return 'waiver_optimize_faab';
    }

    public function description(): string
    {
        return 'Suggest FAAB bids for candidates based on projections delta and trend.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['league_id', 'roster_id', 'season', 'week', 'budget'],
            'properties' => [
                'league_id' => ['type' => 'string'],
                'roster_id' => ['type' => 'integer'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
                'budget' => ['type' => 'number', 'minimum' => 0],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'candidates' => ['type' => 'array', 'items' => ['type' => 'string']],
                'max_candidates' => ['type' => 'integer', 'default' => 10],
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
        $rosterId = (int) $arguments['roster_id'];
        $season = (string) $arguments['season'];
        $week = (int) $arguments['week'];
        $budget = (float) $arguments['budget'];
        $max = (int) ($arguments['max_candidates'] ?? 10);
        $candidatesIn = array_map('strval', (array) ($arguments['candidates'] ?? []));

        $rosters = $sdk->getLeagueRosters($leagueId);
        $roster = collect($rosters)->firstWhere('sleeper_roster_id', (string) $rosterId) ?? [];
        $owned = array_map('strval', (array) ($roster['players'] ?? []));

        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);
        $trending = $sdk->getPlayersTrending('add', $sport, 48, 200);
        $trendIndex = [];
        foreach ($trending as $t) {
            $trendIndex[(string) ($t['player_id'] ?? '')] = (int) ($t['count'] ?? 0);
        }

        // Generate candidate pool: either provided or top trending not owned
        $pool = $candidatesIn;
        if (empty($pool)) {
            foreach ($trending as $t) {
                $pid = (string) ($t['player_id'] ?? '');
                if ($pid !== '' && ! in_array($pid, $owned, true)) {
                    $pool[] = $pid;
                }
                if (count($pool) >= $max) {
                    break;
                }
            }
        }

        $recs = [];
        foreach ($pool as $pid) {
            $pid = (string) $pid;
            $proj = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
            $trend = (float) ($trendIndex[$pid] ?? 0);
            $name = $catalog[$pid]['full_name'] ?? trim(($catalog[$pid]['first_name'] ?? '').' '.($catalog[$pid]['last_name'] ?? ''));
            // Heuristic bid: weight projections and trend, scale to budget
            $priority = $proj + $trend / 10000.0;
            $bid = min($budget, max(0.0, round($budget * min(0.5, $priority / 50.0), 0)));
            $recs[] = [
                'player_id' => $pid,
                'name' => $name,
                'projected_points' => $proj,
                'trend_count' => (int) $trend,
                'recommended_bid' => $bid,
            ];
        }

        usort($recs, fn ($a, $b) => ($b['recommended_bid'] <=> $a['recommended_bid']) ?: ($b['projected_points'] <=> $a['projected_points']));

        return ['bids' => $recs];
    }
}
