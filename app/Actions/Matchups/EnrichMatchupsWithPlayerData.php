<?php

namespace App\Actions\Matchups;

use App\Models\Player;
use Illuminate\Support\Collection;

class EnrichMatchupsWithPlayerData
{
    public function execute(array $matchups, int $season, int $week): array
    {
        if (empty($matchups)) {
            return [];
        }

        $playerIds = collect($matchups)
            ->flatten(1)
            ->flatMap(function ($matchup) {
                $ids = [];
                $ids = array_merge($ids, $matchup['starters']);
                $ids = array_merge($ids, $matchup['players']);

                return $ids;
            })
            ->filter()
            ->unique()
            ->values();

        if ($playerIds->isEmpty()) {
            return $matchups;
        }

        $players = Player::whereIn('player_id', $playerIds)
            ->with([
                'projections' => function ($query) use ($season, $week) {
                    $query->where('season', $season)->where('week', $week);
                },
                'stats' => function ($query) use ($season, $week) {
                    $query->where('season', $season)->where('week', $week);
                },
            ])
            ->get()
            ->keyBy('player_id');

        foreach ($matchups as &$matchup) {
            foreach ($matchup as &$team) {
                $team = $this->enrichTeam($team, $players);
            }
        }

        return $matchups;
    }

    private function enrichTeam(array $team, Collection $players): array
    {
        return collect($team)
            ->transform(function ($value, $key) use ($players) {
                if ($key === 'starters' || $key === 'players') {
                    return collect($value)
                        ->map(fn ($player_id) => $this->enrichPlayer($player_id, $players))
                        ->all();
                }

                return $value;
            })
            ->all();
    }

    private function enrichPlayer(string $player_id, Collection $players): array
    {
        $player = $players->get($player_id);

        $projection = $player->projections->first();
        $stats = $player->stats->first();

        return [
            'player_id' => $player->player_id,
            'name' => $player->full_name ?? ($player->first_name.' '.$player->last_name),
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'position' => $player->position,
            'team' => $player->team,
            'fantasy_positions' => $player->fantasy_positions,
            'active' => $player->active,
            'injury_status' => $player->injury_status,
            'projection' => $projection,
            'stats' => $stats,
        ];
    }
}
