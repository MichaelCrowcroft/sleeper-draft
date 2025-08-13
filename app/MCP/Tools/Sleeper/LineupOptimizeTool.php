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
            'required' => ['league_id', 'roster_id'],
            'properties' => [
                'league_id' => ['type' => 'string'],
                'roster_id' => ['type' => 'integer'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'strategy' => ['type' => 'string', 'enum' => ['median', 'ceiling', 'floor'], 'default' => 'median'],
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
            return in_array($playerPosition, ['RB', 'WR', 'TE'], true);
        }
        if ($slot === 'SUPER_FLEX' || $slot === 'SUPERFLEX' || $slot === 'SFLEX') {
            return in_array($playerPosition, ['QB', 'RB', 'WR', 'TE'], true);
        }
        if ($slot === 'REC_FLEX') {
            return in_array($playerPosition, ['WR', 'TE'], true);
        }
        if (str_contains($slot, 'WR_RB')) {
            return in_array($playerPosition, ['WR', 'RB'], true);
        }
        if (str_contains($slot, 'WR_TE')) {
            return in_array($playerPosition, ['WR', 'TE'], true);
        }
        if (str_contains($slot, 'RB_TE')) {
            return in_array($playerPosition, ['RB', 'TE'], true);
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
        // Resolve season/week if not provided
        $state = $sdk->getState($sport);
        $season = (string) ($arguments['season'] ?? ($state['season'] ?? date('Y')));
        $week = (int) ($arguments['week'] ?? (int) ($state['week'] ?? 1));

        $league = $sdk->getLeague($leagueId);
        $rosters = $sdk->getLeagueRosters($leagueId);
        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);

        $roster = collect($rosters)->firstWhere('roster_id', $rosterId) ?? [];
        $players = array_map('strval', (array) ($roster['players'] ?? []));

        $slots = array_values(array_filter((array) ($league['roster_positions'] ?? []), function ($p) {
            $p = strtoupper((string) $p);

            return ! in_array($p, ['BN', 'IR', 'TAXI'], true);
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

        // Dynamic programming assignment: maximize sum points with eligibility constraints
        // Build bipartite compatibility list
        $nSlots = count($slots);
        $mPlayers = count($candidates);
        // Simple Hungarian-like greedy with improvement: try best eligible per slot, then swap improvements
        $starters = [];
        $assigned = [];
        $remaining = $candidates;
        // initial greedy
        foreach ($slots as $slotIdx => $slot) {
            $eligible = array_values(array_filter($remaining, fn ($c) => $this->isEligible($c['position'], (string) $slot)));
            usort($eligible, fn ($a, $b) => $b['points'] <=> $a['points']);
            if (! empty($eligible)) {
                $choice = $eligible[0];
                $starters[$slotIdx] = $choice;
                $assigned[$choice['player_id']] = true;
            }
        }
        // improvement pass: for each slot consider swapping with unassigned to improve total
        foreach ($slots as $slotIdx => $slot) {
            $current = $starters[$slotIdx] ?? null;
            foreach ($candidates as $cand) {
                if (! $this->isEligible($cand['position'], (string) $slot)) {
                    continue;
                }
                if (! isset($assigned[$cand['player_id']]) && ($current === null || $cand['points'] > $current['points'])) {
                    if ($current !== null) {
                        unset($assigned[$current['player_id']]);
                    }
                    $starters[$slotIdx] = $cand;
                    $assigned[$cand['player_id']] = true;
                    $current = $cand;
                }
            }
        }
        ksort($starters);
        $starterIds = array_values(array_map(fn ($c) => $c['player_id'], array_filter($starters)));

        $bench = array_values(array_diff($players, $starterIds));
        $expectedPoints = array_sum(array_map(function ($pid) use ($projections) {
            return (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
        }, $starterIds));

        return [
            'starters' => $starterIds,
            'bench' => $bench,
            'expected_points' => $expectedPoints,
        ];
    }
}
