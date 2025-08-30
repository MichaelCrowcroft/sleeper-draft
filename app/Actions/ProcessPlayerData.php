<?php

namespace App\Actions;

use Illuminate\Support\Carbon;

class ProcessPlayerData
{
    public function execute(array $players, string $sport, int $chunkSize): array
    {
        $now = Carbon::now();
        $chunks = [];

        foreach (array_chunk($players, $chunkSize, true) as $chunk) {
            $rows = [];

            foreach ($chunk as $playerId => $player) {
                $rows[] = $this->transformPlayer($player, $playerId, $sport, $now);
            }

            if (! empty($rows)) {
                $chunks[] = $rows;
            }
        }

        return $chunks;
    }

    private function transformPlayer(array $player, string $playerId, string $sport, Carbon $timestamp): array
    {
        return [
            'player_id' => (string) $playerId,
            'sport' => (string) ($player['sport'] ?? $sport),

            'first_name' => $player['first_name'] ?? null,
            'last_name' => $player['last_name'] ?? null,
            'full_name' => $player['full_name'] ?? (($player['first_name'] ?? '').(isset($player['last_name']) ? ' '.$player['last_name'] : '')) ?: null,

            'search_first_name' => $player['search_first_name'] ?? null,
            'search_last_name' => $player['search_last_name'] ?? null,
            'search_full_name' => $player['search_full_name'] ?? null,
            'search_rank' => isset($player['search_rank']) ? (int) $player['search_rank'] : null,

            'team' => $player['team'] ?? null,
            'position' => $player['position'] ?? null,
            'fantasy_positions' => isset($player['fantasy_positions']) ? json_encode($player['fantasy_positions']) : null,

            'status' => $player['status'] ?? null,
            'active' => array_key_exists('active', $player) ? (bool) $player['active'] : null,

            'number' => isset($player['number']) ? (int) $player['number'] : null,
            'age' => isset($player['age']) ? (int) $player['age'] : null,
            'years_exp' => isset($player['years_exp']) ? (int) $player['years_exp'] : null,
            'college' => $player['college'] ?? null,
            'birth_date' => $player['birth_date'] ?? null,
            'birth_city' => $player['birth_city'] ?? null,
            'birth_state' => $player['birth_state'] ?? null,
            'birth_country' => $player['birth_country'] ?? null,
            'height' => $player['height'] ?? null,
            'weight' => isset($player['weight']) ? (int) $player['weight'] : null,

            'depth_chart_position' => $player['depth_chart_position'] ?? null,
            'depth_chart_order' => isset($player['depth_chart_order']) ? (int) $player['depth_chart_order'] : null,

            'injury_status' => $player['injury_status'] ?? null,
            'injury_body_part' => $player['injury_body_part'] ?? null,
            'injury_start_date' => $player['injury_start_date'] ?? null,
            'injury_notes' => $player['injury_notes'] ?? null,

            'news_updated' => isset($player['news_updated']) ? (int) $player['news_updated'] : null,
            'hashtag' => $player['hashtag'] ?? null,

            'espn_id' => $player['espn_id'] ?? null,
            'yahoo_id' => $player['yahoo_id'] ?? null,
            'rotowire_id' => $player['rotowire_id'] ?? null,
            'pff_id' => $player['pff_id'] ?? null,
            'sportradar_id' => $player['sportradar_id'] ?? null,
            'fantasy_data_id' => $player['fantasy_data_id'] ?? null,
            'gsis_id' => $player['gsis_id'] ?? null,

            'raw' => json_encode($player),

            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }
}
