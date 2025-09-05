<?php

use App\MCP\Tools\FetchMatchupsTool;
use App\MCP\Tools\FetchUserLeaguesTool;
use App\Models\PlayerProjections;
use App\Models\PlayerStats;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

new class extends Component
{
    public array $leagues = [];

    public array $leagueDetails = [];

    public bool $loading = true;

    public string $error = '';

    public ?int $resolvedWeek = null;

    public function mount(): void
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData(): void
    {
        try {
            $user = Auth::user();

            if (! $user || (! $user->sleeper_username && ! $user->sleeper_user_id)) {
                $this->error = 'Please set up your Sleeper username in your profile settings to view your leagues.';
                $this->loading = false;

                return;
            }

            // Resolve current NFL week
            $this->resolveCurrentWeek();

            // Determine user identifier (username takes priority if both exist)
            $userIdentifier = $user->sleeper_username ?: $user->sleeper_user_id;

            // Fetch user's leagues
            try {
                $leaguesTool = app(FetchUserLeaguesTool::class);
                $leaguesResult = $leaguesTool->execute([
                    'user_identifier' => $userIdentifier,
                    'sport' => 'nfl',
                ]);

                if (! $leaguesResult['success']) {
                    $this->error = 'Unable to connect to Sleeper API. Please check your username and try again.';
                    $this->loading = false;

                    return;
                }

                if (empty($leaguesResult['data'])) {
                    $this->error = 'No leagues found for your Sleeper account.';
                    $this->loading = false;

                    return;
                }

                $this->leagues = $leaguesResult['data'];

                // Fetch details for each league (rosters and matchups)
                $this->loadLeagueDetails();
            } catch (\Exception $e) {
                $this->error = 'Unable to connect to Sleeper API. Please check your username and try again.';
                $this->loading = false;

                return;
            }

        } catch (\Exception $e) {
            $this->error = 'Failed to load dashboard data: '.$e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    private function resolveCurrentWeek(): void
    {
        try {
            $response = Sleeper::state()->current('nfl');
            if ($response->successful()) {
                $state = $response->json();
                $week = isset($state['week']) ? (int) $state['week'] : null;
                $this->resolvedWeek = ($week && $week >= 1 && $week <= 18) ? $week : null;
            }
        } catch (\Throwable $e) {
            $this->resolvedWeek = null;
        }
    }

    private function loadLeagueDetails(): void
    {
        $this->leagueDetails = [];

        foreach ($this->leagues as $league) {
            try {
                $leagueId = $league['id'];

                // Fetch all rosters for the league
                $rosters = $this->fetchLeagueRosters($leagueId);

                // Fetch current week matchups
                $matchups = [];
                $currentWeek = null;
                try {
                    $matchupsTool = app(FetchMatchupsTool::class);
                    $matchupsResult = $matchupsTool->execute([
                        'league_id' => $leagueId,
                        'sport' => 'nfl',
                    ]);
                    // Tool returns direct array: ['league_id' => ..., 'week' => int, 'matchups' => [...]]
                    $matchups = is_array($matchupsResult) ? ($matchupsResult['matchups'] ?? []) : [];
                    $currentWeek = is_array($matchupsResult) ? ($matchupsResult['week'] ?? null) : null;
                } catch (\Exception $e) {
                    logger('Failed to load matchups for league '.$leagueId, [
                        'error' => $e->getMessage(),
                    ]);
                }

                // Enhance matchups with roster data
                $matchups = $this->enhanceMatchupsWithRosters($matchups, $rosters);

                $this->leagueDetails[$leagueId] = [
                    'league' => $league,
                    'rosters' => $rosters,
                    'matchups' => $matchups,
                    'current_week' => $currentWeek,
                ];

            } catch (\Exception $e) {
                // Log error but continue with other leagues
                logger('Failed to load details for league '.$league['id'], [
                    'error' => $e->getMessage(),
                ]);

                $this->leagueDetails[$league['id']] = [
                    'league' => $league,
                    'rosters' => [],
                    'matchups' => [],
                    'current_week' => null,
                    'error' => 'Failed to load league details',
                ];
            }
        }
    }

    private function fetchLeagueRosters(string $leagueId): array
    {
        try {
            $response = Sleeper::leagues()->rosters($leagueId);
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            logger('Failed to fetch rosters for league '.$leagueId, [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    private function enhanceMatchupsWithRosters(array $matchups, array $rosters): array
    {
        $rosterMap = [];
        foreach ($rosters as $roster) {
            $ownerId = $roster['owner_id'] ?? null;
            if ($ownerId) {
                $rosterMap[$ownerId] = $roster;
            }
        }

        foreach ($matchups as &$matchup) {
            if (isset($matchup['teams'])) {
                foreach ($matchup['teams'] as &$team) {
                    $user = $team['user'] ?? null;
                    if ($user) {
                        $userId = $user['user_id'] ?? null;
                        $username = $user['username'] ?? null;

                        // Find roster by user_id or username
                        $matchingRoster = null;
                        if ($userId && isset($rosterMap[$userId])) {
                            $matchingRoster = $rosterMap[$userId];
                        } elseif ($username) {
                            foreach ($rosterMap as $roster) {
                                $rosterOwner = $roster['owner'] ?? null;
                                if ($rosterOwner && ($rosterOwner['username'] ?? null) === $username) {
                                    $matchingRoster = $roster;
                                    break;
                                }
                            }
                        }

                        if ($matchingRoster) {
                            // Prefer starters; fallback to all players
                            $team['roster'] = $matchingRoster['starters'] ?? ($matchingRoster['players'] ?? []);
                        }
                    }
                }
            }
        }

        return $matchups;
    }

    private function getUserRoster(array $rosters): ?array
    {
        $user = Auth::user();
        $userId = $user->sleeper_user_id ?: $user->sleeper_username;

        foreach ($rosters as $roster) {
            if (($roster['owner_id'] ?? null) === $userId ||
                ($roster['owner']['username'] ?? null) === $user->sleeper_username) {
                return $roster;
            }
        }

        return null;
    }

    private function getUserMatchup(array $pairedMatchups): ?array
    {
        $user = Auth::user();
        $userId = $user->sleeper_user_id ?? null;
        $username = $user->sleeper_username ?? null;

        foreach ($pairedMatchups as $pair) {
            $teams = $pair['teams'] ?? [];
            foreach ($teams as $index => $team) {
                $teamUser = $team['user'] ?? null;
                if (! $teamUser) {
                    continue;
                }

                $matchesUser = false;
                if ($userId && (($teamUser['user_id'] ?? null) === $userId)) {
                    $matchesUser = true;
                }
                if (! $matchesUser && $username && (($teamUser['username'] ?? null) === $username)) {
                    $matchesUser = true;
                }

                if ($matchesUser) {
                    $opponent = $teams[$index === 0 ? 1 : 0] ?? null;
                    $oppUser = $opponent['user'] ?? null;

                    // Get detailed matchup info with actual vs projected points
                    $detailedMatchup = $this->getDetailedMatchupInfo($pair, $team, $opponent);

                    return [
                        'matchup_id' => $pair['matchup_id'] ?? null,
                        'team_name' => $teamUser['team_name'] ?? ($teamUser['display_name'] ?? ($teamUser['username'] ?? 'Your Team')),
                        'points' => $team['points'] ?? 0,
                        'opponent_details' => [
                            'team_name' => $oppUser['team_name'] ?? ($oppUser['display_name'] ?? ($oppUser['username'] ?? 'Opponent Team')),
                            'points' => $opponent['points'] ?? 0,
                        ],
                        'detailed_info' => $detailedMatchup,
                    ];
                }
            }
        }

        return null;
    }

    private function getDetailedMatchupInfo(array $pair, array $userTeam, array $opponentTeam): array
    {
        $currentWeek = $pair['week'] ?? null;
        $season = $pair['season'] ?? date('Y');

        if (! $currentWeek) {
            return [
                'user_actual_points' => 0,
                'user_projected_points' => 0,
                'user_total_projected' => 0,
                'user_players_played' => 0,
                'user_total_players' => 0,
                'opponent_actual_points' => 0,
                'opponent_projected_points' => 0,
                'opponent_total_projected' => 0,
                'opponent_players_played' => 0,
                'opponent_total_players' => 0,
            ];
        }

        // Get user team roster
        $userRoster = $userTeam['roster'] ?? [];
        $opponentRoster = $opponentTeam['roster'] ?? [];

        // Calculate points for user team
        $userPoints = $this->calculateTeamPoints($userRoster, $season, $currentWeek);
        $opponentPoints = $this->calculateTeamPoints($opponentRoster, $season, $currentWeek);

        return array_merge($userPoints, [
            'opponent_actual_points' => $opponentPoints['actual_points'],
            'opponent_projected_points' => $opponentPoints['projected_points'],
            'opponent_total_projected' => $opponentPoints['total_projected'],
            'opponent_players_played' => $opponentPoints['players_played'],
            'opponent_total_players' => $opponentPoints['total_players'],
        ]);
    }

    private function calculateTeamPoints(array $roster, int $season, int $week): array
    {
        $actualPoints = 0;
        $projectedPoints = 0;
        $playersPlayed = 0;
        $totalPlayers = count($roster);

        foreach ($roster as $playerId) {
            if (! is_string($playerId)) {
                continue;
            }

            // Check if player has actual stats for this week
            $actualStat = PlayerStats::where('player_id', $playerId)
                ->where('season', $season)
                ->where('week', $week)
                ->first();

            if ($actualStat) {
                $actualPts = null;
                if (isset($actualStat->stats) && is_array($actualStat->stats) && array_key_exists('pts_ppr', $actualStat->stats)) {
                    $actualPts = $actualStat->stats['pts_ppr'];
                } elseif (isset($actualStat->pts_ppr)) {
                    $actualPts = $actualStat->pts_ppr;
                }

                if ($actualPts !== null) {
                    $actualPoints += (float) $actualPts;
                }
                $playersPlayed++;
            } else {
                // Get projected points for players who haven't played
                $projection = PlayerProjections::where('player_id', $playerId)
                    ->where('season', $season)
                    ->where('week', $week)
                    ->first();

                if ($projection) {
                    $projPts = null;
                    if (isset($projection->stats) && is_array($projection->stats) && array_key_exists('pts_ppr', $projection->stats)) {
                        $projPts = $projection->stats['pts_ppr'];
                    } elseif (isset($projection->pts_ppr)) {
                        $projPts = $projection->pts_ppr;
                    }

                    if ($projPts !== null) {
                        $projectedPoints += (float) $projPts;
                    }
                }
            }
        }

        return [
            'actual_points' => round($actualPoints, 2),
            'projected_points' => round($projectedPoints, 2),
            'total_projected' => round($actualPoints + $projectedPoints, 2),
            'players_played' => $playersPlayed,
            'total_players' => $totalPlayers,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="lg">Dashboard</flux:heading>
            <p class="text-muted-foreground mt-1">Your Sleeper Fantasy Football Leagues</p>
        </div>

        @if ($resolvedWeek)
            <flux:badge variant="secondary" size="sm">
                NFL Week {{ $resolvedWeek }}
            </flux:badge>
        @endif
    </div>

    <!-- Quick Stats Row -->
    @if (!$loading && !empty($leagues))
        <div class="grid gap-4 md:grid-cols-3">
            <flux:callout class="text-center">
                <div class="text-2xl font-bold text-primary">{{ count($leagues) }}</div>
                <div class="text-sm text-muted-foreground">Active Leagues</div>
            </flux:callout>

            <flux:callout class="text-center">
                <div class="text-2xl font-bold text-primary">
                    {{ collect($leagueDetails)->filter(fn($details) => $this->getUserMatchup($details['matchups'] ?? []))->count() }}
                </div>
                <div class="text-sm text-muted-foreground">Matchups This Week</div>
            </flux:callout>

            <flux:callout class="text-center">
                <div class="text-2xl font-bold text-primary">
                    {{ collect($leagueDetails)->sum(fn($details) => count($this->getUserRoster($details['rosters'] ?? [])['starters'] ?? [])) }}
                </div>
                <div class="text-sm text-muted-foreground">Total Starters</div>
            </flux:callout>
        </div>
    @endif

    <!-- Loading State -->
    @if ($loading)
        <div class="flex items-center justify-center py-12">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
            <span class="ml-3 text-lg">Loading your leagues...</span>
        </div>
    @elseif ($error)
        <flux:callout variant="danger">
            ⚠️ {{ $error }}
        </flux:callout>
    @elseif (empty($leagues))
        <flux:callout>
            ℹ️ No leagues found. Make sure your Sleeper username is set up in your profile.
        </flux:callout>
    @else
        <!-- Leagues Grid -->
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($leagueDetails as $leagueId => $details)
                @php
                    $userRoster = $this->getUserRoster($details['rosters']);
                    $userMatchup = $this->getUserMatchup($details['matchups']);
                @endphp

                <x-league-card
                    :leagueId="$leagueId"
                    :league="$details['league']"
                    :rosters="$details['rosters']"
                    :matchups="$details['matchups']"
                    :currentWeek="$details['current_week']"
                    :userMatchup="$userMatchup"
                    :userRoster="$userRoster"
                    :error="$details['error'] ?? null"
                />
            @endforeach
        </div>
    @endif
</div>
