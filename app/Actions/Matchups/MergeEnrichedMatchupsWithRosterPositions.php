<?php

namespace App\Actions\Matchups;

use App\Actions\Sleeper\FetchLeague;

class MergeEnrichedMatchupsWithRosterPositions
{
    /**
     * Attach ordered starter slots (excluding bench-like slots) to each team in enriched matchups
     * and map starters into those slots by index. If there are more slots than starters, fill with nulls.
     *
     * @param  array<string,array<int,array<string,mixed>>>  $enriched_matchups  Output of EnrichMatchupsWithPlayerData
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function execute(array $enriched_matchups, string $league_id): array
    {
        $league = (new FetchLeague)->execute($league_id);
        $roster_positions = $league['roster_positions'] ?? [];

        // Filter out bench-like slots
        $starter_slots = array_values(array_filter((array) $roster_positions, function ($slot) {
            $slot = strtoupper((string) $slot);

            return ! in_array($slot, ['BN', 'BENCH', 'TAXI', 'IR', 'RESERVE'], true);
        }));

        foreach ($enriched_matchups as $matchup_id => &$teams) {
            foreach ($teams as &$team) {
                if (! is_array($team) || ! isset($team['owner_id'])) {
                    continue;
                }

                $team['roster_slots'] = $starter_slots;

                // Build slot -> player mapping by index order
                $starters = [];
                $raw_starters = (array) ($team['starters'] ?? []);
                $count_slots = count($starter_slots);

                for ($i = 0; $i < $count_slots; $i++) {
                    $starters[$i] = $raw_starters[$i] ?? null; // keep null if missing
                }

                $team['starters'] = $starters; // indexed array where value may be player array or null
            }
        }

        return $enriched_matchups;
    }
}
