<?php

namespace App\Actions\Players;

use App\Actions\Sleeper\DetermineCurrentWeek;
use App\Actions\Sleeper\BuildLeagueRosterOwnerMap;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuildPlayersTable
{
    public function __construct(
        public PlayerTableQuery $playerTableQuery,
        public ComputeRankings $computeRankings,
        public EnrichPlayersForTable $enrichPlayers,
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

        // Exclusions for free agents only
        $excludeIds = [];
        $rosterMap = [];
        if ($faOnly && is_string($leagueId) && $leagueId !== '') {
            $rosterMap = $this->buildLeagueRosterOwnerMap->execute($leagueId);
            $excludeIds = array_keys($rosterMap);
        }

        // Build and paginate query
        $query = $this->playerTableQuery->build([
            'search' => $search,
            'position' => $position,
            'team' => $team,
            'activeOnly' => true,
            'excludePlayerIds' => $excludeIds,
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection,
        ]);
        // Restrict to playable positions via scope later before pagination

        $players = $this->playerTableQuery->paginate(
            $this->playerTableQuery->addListEagerLoads(
                $query->playablePositions()
            ),
            $perPage
        );

        // Rankings
        $seasonRankLookup = $this->computeRankings->season2024();
        $resolvedWeek = $this->determineCurrentWeek->execute('nfl')['week'] ?? null;
        $weeklyRankLookup = [];
        if ($resolvedWeek) {
            $weeklyRankLookup = $this->computeRankings->weekly(2025, (int) $resolvedWeek);
        }

        // Enrich rows
        $this->enrichPlayers->execute(
            $players,
            $resolvedWeek ? (int) $resolvedWeek : null,
            $seasonRankLookup,
            $weeklyRankLookup,
            collect($rosterMap)
        );

        return $players;
    }
}
