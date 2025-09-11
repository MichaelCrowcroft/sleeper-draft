<?php

namespace App\Actions\Players;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EnrichPlayersForTable
{
    /**
     * Enrich a list/paginator of Player models with computed fields for the table.
     *
     * @param  iterable  $players  Eloquent models (can be a Paginator)
     * @param  int|null  $resolvedWeek  Current NFL week or null
     * @param  array  $weeklyRankLookup  [player_id => rank]
     * @param  Collection  $rosteredPlayers  Map of player_id => ['owner'=>..., 'roster_id'=>..., 'owner_id'=>...]
     * @return mixed Returns the same $players reference for convenience
     */
    public function execute(
        LengthAwarePaginator|iterable $players,
        ?int $resolvedWeek,
        array $weeklyRankLookup,
        Collection $rosteredPlayers
    ): mixed {
        foreach ($players as $player) {
            // Season summaries
            $player->season_2024_summary = $player->getSeason2024Summary();
            $player->season_2025_projections = $player->getSeason2025ProjectionSummary();
            $player->season_2024_target_share_avg = $player->getSeason2024AverageTargetShare();

            // Weekly rank
            $player->weekly_position_rank = $weeklyRankLookup[$player->player_id] ?? null;

            // Projected points for current week
            $player->proj_pts_week = null;
            if ($resolvedWeek) {
                $weekly = null;
                if ($player->relationLoaded('projections2025')) {
                    $weekly = optional($player->projections2025)->firstWhere('week', $resolvedWeek);
                }
                if ($weekly) {
                    $stats = is_array($weekly->stats ?? null) ? $weekly->stats : null;
                    if ($stats && isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr'])) {
                        $player->proj_pts_week = (float) $stats['pts_ppr'];
                    } elseif (isset($weekly->pts_ppr) && is_numeric($weekly->pts_ppr)) {
                        $player->proj_pts_week = (float) $weekly->pts_ppr;
                    }
                } else {
                    $player->proj_pts_week = $player->getProjectedPointsForWeek(2025, (int) $resolvedWeek);
                }
            }

            // 2025 projection averages
            $player->season_2025_avg_metrics = $player->getSeason2025ProjectionsAverages();

            // Roster info
            $rosterInfo = $rosteredPlayers->get($player->player_id);
            $player->owner = $rosterInfo ? $rosterInfo['owner'] : 'Free Agent';
            $player->is_rostered = $rosterInfo !== null;
        }

        return $players;
    }
}
