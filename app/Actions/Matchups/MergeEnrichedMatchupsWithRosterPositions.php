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
                    $player = $raw_starters[$i] ?? null;
                    if (is_array($player)) {
                        $player['is_starter'] = true;
                        // Attach the slot label so frontend can show where they start
                        $player['slot_label'] = $starter_slots[$i] ?? null;
                    }
                    $starters[$i] = $player; // keep null if missing
                }

                $team['starters'] = $starters; // indexed array where value may be player array or null

                // Identify starters by player_id from aligned starters array
                $starter_ids = array_values(array_filter(array_map(function ($p) {
                    return is_array($p) && isset($p['player_id']) ? $p['player_id'] : null;
                }, $team['starters'])));

                // Mark all players with starter flag and compute bench players
                $players_marked = [];
                foreach ($raw_players as $player) {
                    if (! is_array($player)) {
                        // Preserve non-array entries as-is
                        $players_marked[] = $player;

                        continue;
                    }

                    $player['is_starter'] = in_array($player['player_id'] ?? null, $starter_ids, true);
                    $players_marked[] = $player;
                }

                $team['players'] = $players_marked;

                $bench_players = array_values(array_filter($players_marked, function ($p) use ($starter_ids) {
                    return is_array($p)
                        ? ! in_array($p['player_id'] ?? null, $starter_ids, true)
                        : false;
                }));

                // Ensure bench players are explicitly marked
                foreach ($bench_players as &$bp) {
                    if (is_array($bp)) {
                        $bp['is_starter'] = false;
                    }
                }

                $team['bench_players'] = $bench_players;
                $team['bench_slots'] = $bench_slots;
            }
        }

        return $enriched_matchups;
    }
}
