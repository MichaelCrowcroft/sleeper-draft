<?php

namespace App\Http\Controllers;

use App\MCP\Tools\FetchRosterTool;
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

        // Build query
        $query = Player::query()
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

        // Get players for current page with relationships
        $players = $query->with(['stats2024', 'projections2025'])->paginate(25);

        // Get summary statistics
        $stats = $this->getPlayerStats();

        // Get available filters
        $availablePositions = $this->getAvailablePositions();
        $availableTeams = $this->getAvailableTeams();

        // Get user's leagues if authenticated
        $leagues = [];
        $leagueRosters = [];

        if (Auth::check()) {
            $leagues = $this->getUserLeagues();
            if ($selectedLeagueId) {
                $leagueRosters = $this->getLeagueRosters($selectedLeagueId);
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
        return Cache::remember("league_rosters_{$leagueId}", now()->addMinutes(10), function () use ($leagueId) {
            try {
                $rostersTool = app(FetchRosterTool::class);
                $result = $rostersTool->execute([
                    'league_id' => $leagueId,
                    'include_player_details' => false,
                    'include_owner_details' => true,
                ]);

                return $result['success'] ? $result['data'] : [];
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
            $players = array_merge(
                $roster['starters'] ?? [],
                $roster['reserve'] ?? [],
                $roster['taxi'] ?? []
            );

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
        // Get the next week (current NFL week + 1, or week 1 if preseason)
        $currentWeek = $this->getCurrentNFLWeek();
        $nextWeek = $currentWeek + 1;

        // Get projection for next week
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
        // This is a simplified implementation
        // In a real application, you'd want to use an NFL API or calculate based on season start
        $now = now();
        $seasonStart = now()->setMonth(9)->setDay(1); // Approximate NFL season start

        if ($now < $seasonStart) {
            return 1; // Preseason
        }

        $weeksSinceStart = $seasonStart->diffInWeeks($now);

        // NFL regular season is 18 weeks, plus preseason
        return min(max(1, $weeksSinceStart + 1), 18);
    }
}
