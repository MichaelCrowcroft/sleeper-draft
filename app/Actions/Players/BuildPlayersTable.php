<?php

namespace App\Actions\Players;

use App\Actions\Sleeper\BuildLeagueRosterOwnerMap;
use App\Actions\Sleeper\DetermineCurrentWeek;
use App\Models\PlayerStats;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuildPlayersTable
{
    public function __construct(
        public PlayerTableQuery $playerTableQuery,
        public BuildLeagueRosterOwnerMap $buildLeagueRosterOwnerMap,
        public DetermineCurrentWeek $determineCurrentWeek,
    ) {}

    /**
     * Build a paginated, enriched players list for table display.
     *
     * Options:
     * - search, position, team, sortBy, sortDirection
     * - league_id (string|null), fa_only (bool)
     * - per_page (int, default 25)
     */
    public function execute(array $options = []): LengthAwarePaginator
    {
        $search = (string) ($options['search'] ?? '');
        $position = (string) ($options['position'] ?? '');
        $team = (string) ($options['team'] ?? '');
        $sortBy = (string) ($options['sortBy'] ?? 'adp');
        $sortDirection = (string) ($options['sortDirection'] ?? 'asc');
        $leagueId = $options['league_id'] ?? null;
        $faOnly = (bool) ($options['fa_only'] ?? false);
        $perPage = (int) ($options['per_page'] ?? 25);

        // Build roster map if a league is selected (for owner display and FA filtering)
        $rosterMap = [];
        $excludeIds = [];
        if (is_string($leagueId) && $leagueId !== '') {
            $rosterMap = $this->buildLeagueRosterOwnerMap->execute($leagueId);
            if ($faOnly) {
                $excludeIds = array_keys($rosterMap);
            }
        }

        // Build base query
        $query = $this->playerTableQuery->build([
            'search' => $search,
            'position' => $position,
            'team' => $team,
            'activeOnly' => true,
            'excludePlayerIds' => $excludeIds,
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection,
        ]);

        $state = $this->determineCurrentWeek->execute('nfl');
        $resolvedWeek = $state['week'] ?? null;
        $season = isset($state['season']) ? (int) $state['season'] : null;
        if ($season && $resolvedWeek) {
            $query->select('players.*')
                ->selectSub(
                    PlayerStats::query()
                        ->select('weekly_ranking')
                        ->whereColumn('player_stats.player_id', 'players.player_id')
                        ->where('season', $season)
                        ->where('week', (int) $resolvedWeek)
                        ->limit(1),
                    'weekly_position_rank'
                );
        }

        // Eager loads and paginate
        $players = $this->playerTableQuery->paginate(
            $this->playerTableQuery->addListEagerLoads(
                $query->playablePositions()
            ),
            $perPage
        );

        // Annotate a single owner_or_free_agent field for convenience
        if (! empty($rosterMap)) {
            foreach ($players as $player) {
                $rosterInfo = $rosterMap[$player->player_id] ?? null;
                $player->owner_or_free_agent = $rosterInfo ? ($rosterInfo['owner'] ?? 'Unknown Owner') : null;
            }
        }

        return $players;
    }
}
