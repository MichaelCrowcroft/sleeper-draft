<?php

namespace App\Actions\Matchups;

class BuildTeamPointsMap
{
    /**
     * Build a [player_id => points] map from an enriched team array.
     * Each value includes actual, projected, used and status.
     *
     * @param  array<string,mixed>  $team
     * @return array<string,array{actual: float, projected: float, used: float, status: string}>
     */
    public function execute(array $team): array
    {
        $points = [];

        foreach (array_merge((array) ($team['starters'] ?? []), (array) ($team['players'] ?? [])) as $p) {
            if (! is_array($p)) {
                continue;
            }

            $player_id = $p['player_id'] ?? null;
            if (! $player_id) {
                continue;
            }

            $actual = $p['stats']['stats']['pts_ppr'] ?? null;
            $projected = $p['projection']['stats']['pts_ppr'] ?? ($p['projection']['pts_ppr'] ?? null);
            $used = ($actual !== null) ? (float) $actual : (float) ($projected ?? 0.0);

            $points[$player_id] = [
                'actual' => (float) ($actual ?? 0.0),
                'projected' => (float) ($projected ?? 0.0),
                'used' => (float) $used,
                'status' => $actual !== null ? 'locked' : 'upcoming',
            ];
        }

        return $points;
    }
}
