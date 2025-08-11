<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class LineupOptimizeTool implements ToolInterface
{
    public function name(): string
    {
        return 'lineup_optimize';
    }

    public function description(): string
    {
        return 'Recommend an optimal lineup using weekly projections and eligibility constraints.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['league_id', 'roster_id', 'season', 'week'],
            'properties' => [
                'league_id' => ['type' => 'string'],
                'roster_id' => ['type' => 'integer'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'strategy' => ['type' => 'string', 'enum' => ['median','ceiling','floor'], 'default' => 'median'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function annotations(): array
    {
        return [];
    }

    private function isEligible(string $playerPosition, string $slot): bool
    {
        $slot = strtoupper($slot);
        $playerPosition = strtoupper($playerPosition);
        if ($slot === 'FLEX') {
            return in_array($playerPosition, ['RB','WR','TE'], true);
        }
        if ($slot === 'SUPER_FLEX' || $slot === 'SUPERFLEX' || $slot === 'SFLEX') {
            return in_array($playerPosition, ['QB','RB','WR','TE'], true);
        }
        if ($slot === 'REC_FLEX') {
            return in_array($playerPosition, ['WR','TE'], true);
        }
        if (str_contains($slot, 'WR_RB')) {
            return in_array($playerPosition, ['WR','RB'], true);
        }
        if (str_contains($slot, 'WR_TE')) {
            return in_array($playerPosition, ['WR','TE'], true);
        }
        if (str_contains($slot, 'RB_TE')) {
            return in_array($playerPosition, ['RB','TE'], true);
        }
        return $playerPosition === $slot;
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

        $league = $sdk->getLeague($leagueId);
        $rosters = $sdk->getLeagueRosters($leagueId);
        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);

        $roster = collect($rosters)->firstWhere('roster_id', $rosterId) ?? [];
        $players = array_map('strval', (array) ($roster['players'] ?? []));

        $slots = array_values(array_filter((array) ($league['roster_positions'] ?? []), function ($p) {
            $p = strtoupper((string) $p);
            return ! in_array($p, ['BN','IR','TAXI'], true);
        }));

        // Build candidate list with projected points
        $candidates = [];
        foreach ($players as $pid) {
            $meta = $catalog[$pid] ?? [];
            $pos = strtoupper((string) ($meta['position'] ?? ''));
            if ($pos === '') {
                continue;
            }
            $pts = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
            $candidates[] = [
                'player_id' => $pid,
                'position' => $pos,
                'points' => $pts,
            ];
        }

        // Greedy fill: for each slot, pick highest projected eligible remaining
        $starters = [];
        $remaining = $candidates;
        foreach ($slots as $slot) {
            $eligible = array_values(array_filter($remaining, fn ($c) => $this->isEligible($c['position'], (string) $slot)));
            usort($eligible, fn ($a, $b) => $b['points'] <=> $a['points']);
            if (! empty($eligible)) {
                $choice = $eligible[0];
                $starters[] = $choice['player_id'];
                // remove from remaining
                $remaining = array_values(array_filter($remaining, fn ($c) => $c['player_id'] !== $choice['player_id']));
            }
        }

        $bench = array_values(array_diff($players, $starters));
        $expectedPoints = array_sum(array_map(function ($pid) use ($projections) {
            return (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
        }, $starters));

        return [
            'starters' => $starters,
            'bench' => $bench,
            'expected_points' => $expectedPoints,
        ];
    }
}
