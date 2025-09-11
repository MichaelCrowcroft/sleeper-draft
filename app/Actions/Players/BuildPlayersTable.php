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

        // Exclusions for free agents only
        $excludeIds = [];
        $rosterMap = [];
        if ($faOnly && is_string($leagueId) && $leagueId !== '') {
            $rosterMap = $this->buildLeagueRosterOwnerMap->execute($leagueId);
            $excludeIds = array_keys($rosterMap);
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
        // Restrict to playable positions via scope later before pagination

        // Rankings (read from DB; no computation)
        $state = $this->determineCurrentWeek->execute('nfl');
        $resolvedWeek = $state['week'] ?? null;
        $season = isset($state['season']) ? (int) $state['season'] : null;
        if ($season && $resolvedWeek) {
            // Include weekly position rank as a selected column via subquery
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

        // Annotate owner info on each item for convenience
        if (! empty($rosterMap)) {
            foreach ($players as $player) {
                $rosterInfo = $rosterMap[$player->player_id] ?? null;
                $player->owner = $rosterInfo ? $rosterInfo['owner'] : 'Free Agent';
                $player->is_rostered = $rosterInfo !== null;
            }
        }

        return $players;
    }
}
