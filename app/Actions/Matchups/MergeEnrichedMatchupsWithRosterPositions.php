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
        $league = new FetchLeague()->execute($league_id);
        $roster_positions = $league['roster_positions'] ?? [];

        // Filter out bench-like slots
        $starter_slots = array_values(array_filter((array) $roster_positions, function ($slot) {
            $slot = strtoupper((string) $slot);

            return ! in_array($slot, ['BN', 'BENCH', 'TAXI', 'IR', 'RESERVE'], true);
        }));

        // Identify bench-like slots
        $bench_slots = array_values(array_filter((array) $roster_positions, function ($slot) {
            $slot = strtoupper((string) $slot);

            return in_array($slot, ['BN', 'BENCH', 'TAXI', 'IR', 'RESERVE'], true);
        }));

        foreach ($enriched_matchups as $matchup_id => &$teams) {
            foreach ($teams as &$team) {
                if (! is_array($team) || ! isset($team['owner_id'])) {
                    continue;
                }

                $team['roster_slots'] = $starter_slots;

                // Build slot -> player mapping by index order for starters
                $starters = [];
                $raw_starters = (array) ($team['starters'] ?? []);
                $raw_players = (array) ($team['players'] ?? []);
                $count_slots = count($starter_slots);

                for ($i = 0; $i < $count_slots; $i++) {
                    $starters[$i] = $raw_starters[$i] ?? null; // keep null if missing
                }

                $team['starters'] = $starters; // indexed array where value may be player array or null

                // Identify bench players (those not in starters)
                $bench_players = [];
                foreach ($raw_players as $player_id) {
                    $is_starter = false;
                    foreach ($raw_starters as $starter_id) {
                        if ($starter_id === $player_id) {
                            $is_starter = true;
                            break;
                        }
                    }

                    if (! $is_starter) {
                        // Find the player data from the enriched starters or players
                        $player_data = null;
                        if (isset($team['starters'])) {
                            foreach ($team['starters'] as $starter) {
                                if (is_array($starter) && isset($starter['player_id']) && $starter['player_id'] === $player_id) {
                                    $player_data = $starter;
                                    break;
                                }
                            }
                        }

                        if (! $player_data && isset($team['players'])) {
                            foreach ($team['players'] as $player) {
                                if (is_array($player) && isset($player['player_id']) && $player['player_id'] === $player_id) {
                                    $player_data = $player;
                                    break;
                                }
                            }
                        }

                        if ($player_data) {
                            $bench_players[] = $player_data;
                        }
                    }
                }

                $team['bench_players'] = $bench_players;
                $team['bench_slots'] = $bench_slots;
            }
        }

        return $enriched_matchups;
    }
}
