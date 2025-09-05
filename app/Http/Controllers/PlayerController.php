<?php

namespace App\Http\Controllers;

use App\MCP\Tools\FetchUserLeaguesTool;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PlayerController extends Controller
{
    /**
     * Display a listing of players with summary information
     */
    public function index(Request $request)
    {
        // Get filter parameters
        $search = $request->get('search');
        $position = $request->get('position');
        $team = $request->get('team');
        $selectedLeagueId = $request->get('league_id');
        $faOnly = (bool) $request->boolean('fa_only');

        // Build query
        $query = Player::query()
            ->whereJsonContains('fantasy_positions', ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('full_name', 'like', '%'.$search.'%');
                });
            })
            ->when($position, function ($q) use ($position) {
                $q->where('position', $position);
            })
            ->when($team, function ($q) use ($team) {
                $q->where('team', $team);
            })
            ->where('active', true);

        // If filtering free agents for a selected league, exclude rostered players at the query level
        $leagueRosters = [];
        if ($selectedLeagueId && $faOnly) {
            $leagueRosters = $this->getLeagueRosters($selectedLeagueId);

            // Build a set of owned player IDs across all rosters
            $ownedIds = [];
            foreach ($leagueRosters as $roster) {
                $playersInRoster = array_merge(
                    (array) ($roster['starters'] ?? []),
                    (array) ($roster['players'] ?? [])
                );
                foreach ($playersInRoster as $pid) {
                    if (! empty($pid)) {
                        $ownedIds[$pid] = true;
                    }
                }
            }

            if (! empty($ownedIds)) {
                $query->whereNotIn('player_id', array_keys($ownedIds));
            }
        }

        // Get players for current page with relationships, ordered by ADP (ascending - lower is better)
        $players = $query->with(['stats2024', 'projections2025'])
            ->orderByRaw('adp IS NULL, adp ASC')
            ->paginate(25);

        // Get summary statistics
        $stats = $this->getPlayerStats();

        // Get available filters
        $availablePositions = $this->getAvailablePositions();
        $availableTeams = $this->getAvailableTeams();

        // Get user's leagues if authenticated
        $leagues = [];
        // $leagueRosters may already be populated above when filtering free agents

        if (Auth::check()) {
            $leagues = $this->getUserLeagues();
            if ($selectedLeagueId) {
                if (empty($leagueRosters)) {
                    $leagueRosters = $this->getLeagueRosters($selectedLeagueId);
                }
            }
        }

        // Add league status to players if league is selected
        if ($selectedLeagueId && ! empty($leagueRosters)) {
            foreach ($players as $player) {
                $player->league_status = $this->getPlayerLeagueStatus($player->player_id, $leagueRosters);
            }
        }

        // Add player stats for each player
        foreach ($players as $player) {
            $player->season_2024_summary = $player->getSeason2024Summary();
            $player->season_2025_projections = $player->getSeason2025ProjectionSummary();
            $player->next_matchup_projection = $this->getNextMatchupProjection($player);
        }

        return view('players.index', compact(
            'players',
            'stats',
            'search',
            'position',
            'team',
            'selectedLeagueId',
            'faOnly',
            'availablePositions',
            'availableTeams',
            'leagues'
        ));
    }

    /**
     * Display the specified player with weekly results
     */
    public function show($playerId)
    {
        $player = Player::where('player_id', $playerId)->firstOrFail();

        // Get player's stats for 2024 season
        $stats2024 = $player->getSeason2024Totals();
        $summary2024 = $player->getSeason2024Summary();

        // Get player's projections for 2025 season
        $projections2025 = $player->getSeason2025ProjectionSummary();

        // Get weekly stats breakdown
        $weeklyStats = $player->getStatsForSeason(2024)->get();

        // Get weekly projections breakdown
        $weeklyProjections = $player->getProjectionsForSeason(2025)->get();

        return view('players.show', compact(
            'player',
            'stats2024',
            'summary2024',
            'projections2025',
            'weeklyStats',
            'weeklyProjections'
        ));
    }

    /**
     * Get player summary statistics
     */
    private function getPlayerStats(): array
    {
        $totalPlayers = Player::where('active', true)->count();

        $playersByPosition = Player::where('active', true)
            ->whereNotNull('position')
            ->selectRaw('position, COUNT(*) as count')
            ->groupBy('position')
            ->orderBy('count', 'desc')
            ->get()
            ->pluck('count', 'position')
            ->toArray();

        $playersByTeam = Player::where('active', true)
            ->whereNotNull('team')
            ->selectRaw('team, COUNT(*) as count')
            ->groupBy('team')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->pluck('count', 'team')
            ->toArray();

        $injuredPlayers = Player::where('active', true)
            ->whereNotNull('injury_status')
            ->where('injury_status', '!=', 'Healthy')
            ->count();

        return [
            'total_players' => $totalPlayers,
            'players_by_position' => $playersByPosition,
            'players_by_team' => $playersByTeam,
            'injured_players' => $injuredPlayers,
        ];
    }

    /**
     * Get available positions for filtering
     */
    private function getAvailablePositions(): array
    {
        return Cache::remember('player_positions', now()->addHours(1), function () {
            return Player::whereNotNull('position')
                ->where('active', true)
                ->distinct()
                ->pluck('position')
                ->sort()
                ->values()
                ->toArray();
        });
    }

    /**
     * Get available teams for filtering
     */
    private function getAvailableTeams(): array
    {
        return Cache::remember('player_teams', now()->addHours(1), function () {
            return Player::whereNotNull('team')
                ->where('active', true)
                ->distinct()
                ->pluck('team')
                ->sort()
                ->values()
                ->toArray();
        });
    }

    /**
     * Get user's leagues
     */
    private function getUserLeagues(): array
    {
        $user = Auth::user();

        if (! $user || (! $user->sleeper_username && ! $user->sleeper_user_id)) {
            return [];
        }

        $userIdentifier = $user->sleeper_username ?: $user->sleeper_user_id;

        return Cache::remember("user_leagues_{$userIdentifier}", now()->addMinutes(5), function () use ($userIdentifier) {
            try {
                $leaguesTool = app(FetchUserLeaguesTool::class);
                $result = $leaguesTool->execute([
                    'user_identifier' => $userIdentifier,
                    'sport' => 'nfl',
                ]);

                return $result['success'] ? $result['data'] : [];
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    /**
     * Get league rosters
     */
    private function getLeagueRosters(string $leagueId): array
    {
        // Bump cache key version to avoid stale data from previous implementation
        return Cache::remember("league_rosters_v2_{$leagueId}", now()->addMinutes(10), function () use ($leagueId) {
            try {
                $rostersResponse = \MichaelCrowcroft\SleeperLaravel\Facades\Sleeper::leagues()->rosters($leagueId);
                if (! $rostersResponse->successful()) {
                    return [];
                }

                $rosters = $rostersResponse->json();
                if (! is_array($rosters)) {
                    return [];
                }

                // Fetch league users once to map owner details
                $usersMap = [];
                $usersResponse = \MichaelCrowcroft\SleeperLaravel\Facades\Sleeper::leagues()->users($leagueId);
                if ($usersResponse->successful()) {
                    $users = $usersResponse->json();
                    if (is_array($users)) {
                        foreach ($users as $user) {
                            $usersMap[$user['user_id'] ?? ''] = [
                                'user_id' => $user['user_id'] ?? null,
                                'username' => $user['username'] ?? null,
                                'display_name' => $user['display_name'] ?? null,
                                'team_name' => $user['metadata']['team_name'] ?? ($user['display_name'] ?? 'Unknown Team'),
                            ];
                        }
                    }
                }

                // Enhance rosters with sanitize players and owner details
                foreach ($rosters as &$roster) {
                    // Attach owner details if available
                    if (! empty($roster['owner_id']) && isset($usersMap[$roster['owner_id']])) {
                        $roster['owner'] = $usersMap[$roster['owner_id']];
                    }

                    // Ensure player arrays are present and sanitized
                    $starters = array_values(array_filter((array) ($roster['starters'] ?? [])));
                    $players = array_values(array_filter((array) ($roster['players'] ?? [])));

                    // Some Sleeper responses may include placeholder values; filter falsy values
                    $roster['starters'] = $starters;
                    $roster['players'] = $players;
                }
                unset($roster);

                return $rosters;
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    /**
     * Get player's league status
     */
    private function getPlayerLeagueStatus(string $playerId, array $leagueRosters): array
    {
        foreach ($leagueRosters as $roster) {
            // Sleeper provides 'players' (entire roster) and 'starters' arrays
            $players = array_merge(
                (array) ($roster['starters'] ?? []),
                (array) ($roster['players'] ?? [])
            );
            // Sanitize values and remove empties
            $players = array_values(array_filter($players, fn ($v) => ! empty($v)));

            if (in_array($playerId, $players)) {
                return [
                    'status' => 'owned',
                    'team_name' => $roster['owner']['team_name'] ?? 'Unknown Team',
                ];
            }
        }

        return ['status' => 'free_agent', 'team_name' => null];
    }

    /**
     * Get next matchup projection for a player
     */
    private function getNextMatchupProjection(Player $player): ?array
    {
        $currentWeek = $this->getCurrentNFLWeek();

        // Prefer current week projection
        $currentWeekProjection = $player->getProjectionsForWeek(2025, $currentWeek);
        if ($currentWeekProjection && isset($currentWeekProjection->stats)) {
            $stats = $currentWeekProjection->stats;

            return [
                'week' => $currentWeek,
                'projected_points' => isset($stats['pts_ppr']) ? round((float) $stats['pts_ppr'], 1) : null,
                'passing_yds' => $stats['pass_yd'] ?? null,
                'passing_tds' => $stats['pass_td'] ?? null,
                'rushing_yds' => $stats['rush_yd'] ?? null,
                'rushing_tds' => $stats['rush_td'] ?? null,
                'receiving_yds' => $stats['rec_yd'] ?? null,
                'receiving_tds' => $stats['rec_td'] ?? null,
            ];
        }

        // Fallback to next week if current week projection is missing
        $nextWeek = $currentWeek + 1;
        $nextWeekProjection = $player->getProjectionsForWeek(2025, $nextWeek);
        if ($nextWeekProjection && isset($nextWeekProjection->stats)) {
            $stats = $nextWeekProjection->stats;

            return [
                'week' => $nextWeek,
                'projected_points' => isset($stats['pts_ppr']) ? round((float) $stats['pts_ppr'], 1) : null,
                'passing_yds' => $stats['pass_yd'] ?? null,
                'passing_tds' => $stats['pass_td'] ?? null,
                'rushing_yds' => $stats['rush_yd'] ?? null,
                'rushing_tds' => $stats['rush_td'] ?? null,
                'receiving_yds' => $stats['rec_yd'] ?? null,
                'receiving_tds' => $stats['rec_td'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Get current NFL week (simplified - in a real app this would be more sophisticated)
     */
    private function getCurrentNFLWeek(): int
    {
        try {
            $response = \MichaelCrowcroft\SleeperLaravel\Facades\Sleeper::state()->current('nfl');
            if ($response->successful()) {
                $state = $response->json();
                $week = (int) ($state['week'] ?? 1);
                if ($week >= 1 && $week <= 18) {
                    return $week;
                }
            }
        } catch (\Throwable $e) {
            // ignore and fall back
        }

        // Fallback to Week 1 if API fails or returns unexpected data
        return 1;
    }
}
