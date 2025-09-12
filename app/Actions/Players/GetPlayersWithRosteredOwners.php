<?php

namespace App\Actions\Players;

use App\Actions\Rosters\GetRosteredPlayers;
use App\Actions\Sleeper\DetermineCurrentWeek;
use App\Models\Player;
use App\Models\PlayerStats;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetPlayersWithRosteredOwners
{
    public function __construct(
        public GetRosteredPlayers $getRosteredPlayers,
        public DetermineCurrentWeek $determineCurrentWeek,
    ) {}

    public function execute(array $options = []): LengthAwarePaginator
    {
        $search = (string) ($options['search'] ?? '');
        $position = (string) ($options['position'] ?? '');
        $team = (string) ($options['team'] ?? '');
        $league_id = $options['league_id'] ?? null;
        $fa_only = (bool) ($options['fa_only'] ?? false);
        $per_page = (int) ($options['per_page'] ?? 25);

        $rostered_players = [];
        $excluded_player_ids = [];

        if(is_string($league_id) && $league_id !== '') {
            $rostered_players = $this->getRosteredPlayers->execute($league_id);
            if ($fa_only) {
                $excluded_player_ids = array_keys($rostered_players);
            }
        }

        $state = $this->determineCurrentWeek->execute('nfl');

        $players = Player::query()
            ->where('active', true)
            ->where('position', $position)
            ->where('team', $team)
            ->search($search)
            ->whereNotIn('player_id', $excluded_player_ids)
            ->selectSub(PlayerStats::query()
                ->select('weekly_ranking')
                ->whereColumn('player_stats.player_id', 'players.player_id')
                ->where('season', $state['season'])
                ->where('week', (int) $state['week'])
                ->limit(1),
                'weekly_position_rank'
            )->playablePositions()
            ->with(['projections2025', 'seasonSummaries'])

        // Annotate a single owner_or_free_agent field for convenience
        if(!empty($rostered_players)) {
            foreach ($players as $player) {
                $roster_info = $rostered_players[$player->player_id] ?? null;
                $player->owner_or_free_agent = $roster_info ? ($roster_info['owner'] ?? 'Unknown Owner') : null;
            }
        }

        return $players;
    }
}
