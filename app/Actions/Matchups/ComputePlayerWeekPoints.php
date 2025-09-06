<?php

namespace App\Actions\Matchups;

use App\Models\Player;

class ComputePlayerWeekPoints
{
    /**
     * Compute player points for the given season/week using actuals when available else projections.
     *
     * @param array<string> $playerIds Sleeper player IDs
     * @return array<string, array{actual: float, projected: float, used: float, status: string}>
     */
    public function execute(array $playerIds, int $season, int $week): array
    {
        $players = Player::query()
            ->whereIn('player_id', $playerIds)
            ->with([
                'stats' => fn ($q) => $q->where('season', $season)->where('week', $week),
                'projections' => fn ($q) => $q->where('season', $season)->where('week', $week),
            ])
            ->get()
            ->keyBy('player_id');

        $result = [];

        foreach ($playerIds as $pid) {
            $player = $players->get($pid);

            $actual = 0.0;
            $projected = 0.0;
            $used = 0.0;
            $status = 'upcoming';

            if ($player) {
                $stats = $player->stats->first();
                $proj = $player->projections->first();

                if ($stats && is_array($stats->stats)) {
                    $actual = isset($stats->stats['pts_ppr']) && is_numeric($stats->stats['pts_ppr'])
                        ? (float) $stats->stats['pts_ppr']
                        : 0.0;
                }

                if ($proj) {
                    $pStats = is_array($proj->stats) ? $proj->stats : [];
                    $projected = isset($pStats['pts_ppr']) && is_numeric($pStats['pts_ppr'])
                        ? (float) $pStats['pts_ppr']
                        : (isset($proj->pts_ppr) && is_numeric($proj->pts_ppr) ? (float) $proj->pts_ppr : 0.0);
                }

                if ($actual > 0.0 || ($stats && !empty($stats->stats))) {
                    $status = 'locked';
                    $used = $actual;
                } else {
                    $status = 'upcoming';
                    $used = $projected;
                }
            }

            $result[(string) $pid] = [
                'actual' => round($actual, 2),
                'projected' => round($projected, 2),
                'used' => round($used, 2),
                'status' => $status,
            ];
        }

        return $result;
    }
}
