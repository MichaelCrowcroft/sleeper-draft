<?php

namespace App\Actions\Matchups;

use Illuminate\Support\Collection;

class SelectOptimalLineup
{
    /**
     * Select the optimal starting lineup to maximize projected points.
     *
     * @param  Collection<string,array{player_id: string, position: string|null, projected_points: float, confidence: float}>  $candidates
     * @param  array<int,array{slot: string, eligible_positions: array<int,string>}>  $roster_slots
     * @return array{starters: array<int,string>, improvement_details: array<string,array{player_id: string, projected_points: float, confidence: float}>}
     */
    public function execute(Collection $candidates, array $roster_slots): array
    {
        $selected_starters = [];
        $used_players = [];
        $improvement_details = [];

        foreach ($roster_slots as $slot_info) {
            $eligible_candidates = $candidates
                ->reject(fn ($candidate) => isset($used_players[$candidate['player_id']]))
                ->filter(function ($candidate) use ($slot_info) {
                    $player_position = strtoupper((string) ($candidate['position'] ?? ''));

                    return in_array($player_position, $slot_info['eligible_positions'], true);
                });

            // If no position-eligible players, allow any remaining player as fallback
            if ($eligible_candidates->isEmpty()) {
                $eligible_candidates = $candidates
                    ->reject(fn ($candidate) => isset($used_players[$candidate['player_id']]));
            }

            // Select highest projected points (tie-breaker: confidence)
            $best_candidate = $eligible_candidates
                ->sortByDesc('projected_points')
                ->sortByDesc('confidence')
                ->first();

            if ($best_candidate) {
                $player_id = $best_candidate['player_id'];
                $selected_starters[] = $player_id;
                $used_players[$player_id] = true;

                $improvement_details[$player_id] = [
                    'player_id' => $player_id,
                    'projected_points' => $best_candidate['projected_points'],
                    'confidence' => $best_candidate['confidence'],
                    'slot' => $slot_info['slot'],
                ];
            }
        }

        return [
            'starters' => $selected_starters,
            'improvement_details' => $improvement_details,
        ];
    }
}
