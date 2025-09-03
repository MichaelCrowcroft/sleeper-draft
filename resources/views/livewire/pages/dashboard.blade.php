<?php

use App\MCP\Tools\FetchUserLeaguesTool;
use App\MCP\Tools\FetchRostersTool;
use App\MCP\Tools\FetchMatchupsTool;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public array $leagues = [];
    public array $leagueDetails = [];
    public bool $loading = true;
    public string $error = '';

    public function mount(): void
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData(): void
    {
        try {
            $user = Auth::user();

            if (!$user || (!$user->sleeper_username && !$user->sleeper_user_id)) {
                $this->error = 'Please set up your Sleeper username in your profile settings to view your leagues.';
                $this->loading = false;
                return;
            }

            // Determine user identifier (username takes priority if both exist)
            $userIdentifier = $user->sleeper_username ?: $user->sleeper_user_id;

            // Fetch user's leagues
            try {
                $leaguesTool = app(FetchUserLeaguesTool::class);
                $leaguesResult = $leaguesTool->execute([
                    'user_identifier' => $userIdentifier,
                    'sport' => 'nfl',
                ]);

                if (!$leaguesResult['success']) {
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
            $this->error = 'Failed to load dashboard data: ' . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    private function loadLeagueDetails(): void
    {
        $this->leagueDetails = [];

        foreach ($this->leagues as $league) {
            try {
                $leagueId = $league['id'];

                // Fetch rosters for this league
                $rosters = [];
                try {
                    $rostersTool = app(FetchRostersTool::class);
                    $rostersResult = $rostersTool->execute([
                        'league_id' => $leagueId,
                        'include_player_details' => true,
                        'include_owner_details' => true,
                    ]);
                    $rosters = $rostersResult['success'] ? $rostersResult['data'] : [];
                } catch (\Exception $e) {
                    logger('Failed to load rosters for league ' . $leagueId, [
                        'error' => $e->getMessage(),
                    ]);
                }

                // Fetch current week matchups
                $matchups = [];
                $currentWeek = null;
                try {
                    $matchupsTool = app(FetchMatchupsTool::class);
                    $matchupsResult = $matchupsTool->execute([
                        'league_id' => $leagueId,
                        'sport' => 'nfl',
                    ]);
                    $matchups = $matchupsResult['success'] ? $matchupsResult['data']['matchups'] : [];
                    $currentWeek = $matchupsResult['success'] ? $matchupsResult['data']['week'] : null;
                } catch (\Exception $e) {
                    logger('Failed to load matchups for league ' . $leagueId, [
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->leagueDetails[$leagueId] = [
                    'league' => $league,
                    'rosters' => $rosters,
                    'matchups' => $matchups,
                    'current_week' => $currentWeek,
                ];

            } catch (\Exception $e) {
                // Log error but continue with other leagues
                logger('Failed to load details for league ' . $league['id'], [
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

    private function getUserMatchup(array $matchups): ?array
    {
        $user = Auth::user();
        $userId = $user->sleeper_user_id ?: $user->sleeper_username;

        foreach ($matchups as $matchup) {
            if (($matchup['owner_details']['user_id'] ?? null) === $userId ||
                ($matchup['owner_details']['username'] ?? null) === $user->sleeper_username) {

                // Find the opponent matchup (same matchup_id)
                $matchupId = $matchup['matchup_id'] ?? null;
                $opponentMatchup = null;

                if ($matchupId) {
                    foreach ($matchups as $otherMatchup) {
                        if (($otherMatchup['matchup_id'] ?? null) === $matchupId &&
                            ($otherMatchup['roster_id'] ?? null) !== ($matchup['roster_id'] ?? null)) {
                            $opponentMatchup = $otherMatchup;
                            break;
                        }
                    }
                }

                // Add opponent details to the matchup
                $matchup['opponent_details'] = $opponentMatchup ? [
                    'team_name' => $opponentMatchup['owner_details']['team_name'] ?? 'Opponent Team',
                    'points' => $opponentMatchup['points'] ?? 0,
                    'starters' => $opponentMatchup['starters_data'] ?? [],
                ] : [
                    'team_name' => 'Opponent Team',
                    'points' => '?',
                    'starters' => [],
                ];

                // Add opponent starters to the matchup for easy access in template
                $matchup['opponent_starters'] = $opponentMatchup['starters_data'] ?? [];

                return $matchup;
            }
        }

        return null;
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="lg">Dashboard</flux:heading>
            <p class="text-muted-foreground mt-1">Your Sleeper Fantasy Football Leagues</p>
        </div>
    </div>

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
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($leagueDetails as $leagueId => $details)
                <flux:callout class="p-6">
                    <div class="space-y-4">
                        <!-- League Header -->
<div>
                            <flux:heading size="md" class="font-semibold">{{ $details['league']['name'] }}</flux:heading>
                            <p class="text-sm text-muted-foreground">League ID: {{ $leagueId }}</p>
                        </div>

                        <!-- User Roster -->
                        @php
                            $userRoster = $this->getUserRoster($details['rosters']);
                        @endphp

                        @if ($userRoster)
                            <div class="space-y-3">
                                <flux:heading size="sm" class="font-medium">Your Team</flux:heading>
                                <div class="text-sm">
                                    <p class="font-medium">{{ $userRoster['owner']['team_name'] ?? 'Your Team' }}</p>

                                    <!-- Starters -->
                                    @if (!empty($userRoster['starters_detailed']))
                                        <div class="mt-2">
                                            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Starters</p>
                                            <div class="mt-1 space-y-1">
                                                @foreach ($userRoster['starters_detailed'] as $starter)
                                                    @php
                                                        $player = $starter['player_data'];
                                                    @endphp
                                                    @if ($player)
                                                        <div class="space-y-1">
                                                            <div class="flex justify-between items-center text-xs">
                                                                <div class="flex flex-col">
                                                                    <span class="font-medium">{{ $player['first_name'] }} {{ $player['last_name'] }}</span>
                                                                    @if(isset($player['injury_status']) && $player['injury_status'] && $player['injury_status'] !== 'Healthy')
                                                                        <span class="text-xs text-red-500 font-medium">{{ $player['injury_status'] }}
                                                                            @if(isset($player['injury_body_part']) && $player['injury_body_part'])
                                                                                ({{ $player['injury_body_part'] }})
                                                                            @endif
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                                <div class="flex flex-col items-end text-muted-foreground">
                                                                    <span>{{ $player['position'] ?? 'N/A' }} • {{ $player['team'] ?? 'FA' }}</span>
                                                                    @if(isset($player['bye_week']) && $player['bye_week'] && isset($details['current_week']) && $details['current_week'] && $player['bye_week'] == $details['current_week'])
                                                                        <span class="text-xs text-orange-500 font-medium">BYE</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <div class="flex justify-between items-center text-xs text-muted-foreground">
                                                                <span>
                                                                    @if(isset($player['adp_formatted']) && $player['adp_formatted'])
                                                                        ADP: {{ $player['adp_formatted'] }}
                                                                    @elseif(isset($player['adp']) && $player['adp'])
                                                                        ADP: {{ number_format($player['adp'], 1) }}
                                                                    @endif
                                                                </span>
                                                                <div class="flex gap-2">
                                                                    @if(isset($player['adds_24h']) && $player['adds_24h'] !== null && $player['adds_24h'] > 0)
                                                                        <span class="text-green-600">+{{ $player['adds_24h'] }}</span>
                                                                    @endif
                                                                    @if(isset($player['drops_24h']) && $player['drops_24h'] !== null && $player['drops_24h'] > 0)
                                                                        <span class="text-red-600">−{{ $player['drops_24h'] }}</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Bench -->
                                    @if (!empty($userRoster['bench_detailed']))
                                        <div class="mt-2">
                                            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Bench</p>
                                            <div class="mt-1 space-y-1">
                                                @foreach ($userRoster['bench_detailed'] as $benchPlayer)
                                                    @php
                                                        $player = $benchPlayer['player_data'];
                                                    @endphp
                                                    @if ($player)
                                                        <div class="space-y-1">
                                                            <div class="flex justify-between items-center text-xs">
                                                                <div class="flex flex-col">
                                                                    <span>{{ $player['first_name'] }} {{ $player['last_name'] }}</span>
                                                                    @if(isset($player['injury_status']) && $player['injury_status'] && $player['injury_status'] !== 'Healthy')
                                                                        <span class="text-xs text-red-500 font-medium">{{ $player['injury_status'] }}
                                                                            @if(isset($player['injury_body_part']) && $player['injury_body_part'])
                                                                                ({{ $player['injury_body_part'] }})
                                                                            @endif
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                                <div class="flex flex-col items-end text-muted-foreground">
                                                                    <span>{{ $player['position'] ?? 'N/A' }} • {{ $player['team'] ?? 'FA' }}</span>
                                                                    @if(isset($player['bye_week']) && $player['bye_week'] && isset($details['current_week']) && $details['current_week'] && $player['bye_week'] == $details['current_week'])
                                                                        <span class="text-xs text-orange-500 font-medium">BYE</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <div class="flex justify-between items-center text-xs text-muted-foreground">
                                                                <span>
                                                                    @if(isset($player['adp_formatted']) && $player['adp_formatted'])
                                                                        ADP: {{ $player['adp_formatted'] }}
                                                                    @elseif(isset($player['adp']) && $player['adp'])
                                                                        ADP: {{ number_format($player['adp'], 1) }}
                                                                    @endif
                                                                </span>
                                                                <div class="flex gap-2">
                                                                    @if(isset($player['adds_24h']) && $player['adds_24h'] !== null && $player['adds_24h'] > 0)
                                                                        <span class="text-green-600">+{{ $player['adds_24h'] }}</span>
                                                                    @endif
                                                                    @if(isset($player['drops_24h']) && $player['drops_24h'] !== null && $player['drops_24h'] > 0)
                                                                        <span class="text-red-600">−{{ $player['drops_24h'] }}</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Current Matchup -->
                        @php
                            $userMatchup = $this->getUserMatchup($details['matchups']);
                        @endphp

                        @if ($userMatchup)
                            <div class="space-y-3">
                                <flux:heading size="sm" class="font-medium">Week {{ $details['current_week'] }} Matchup</flux:heading>
                                <div class="text-sm space-y-2">
                                    <!-- Score Header -->
                                    <div class="flex justify-between items-center font-medium">
                                        <span>{{ $userMatchup['owner_details']['team_name'] ?? 'Your Team' }}</span>
                                        <span>{{ $userMatchup['points'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-muted-foreground text-center text-xs">vs</div>
                                    <div class="flex justify-between items-center font-medium">
                                        <span>{{ $userMatchup['opponent_details']['team_name'] ?? 'Opponent Team' }}</span>
                                        <span>{{ $userMatchup['opponent_points'] ?? '?' }}</span>
                                    </div>

                                    <!-- Your Starters -->
                                    @if (!empty($userMatchup['starters_data']))
                                        <div class="mt-3 pt-2 border-t">
                                            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide mb-1">Your Starters</p>
                                            <div class="space-y-1">
                                                @foreach ($userMatchup['starters_data'] as $starter)
                                                    @if ($starter && isset($starter['first_name']))
                                                        <div class="space-y-1">
                                                            <div class="flex justify-between items-center text-xs">
                                                                <div class="flex flex-col">
                                                                    <span>{{ $starter['first_name'] }} {{ $starter['last_name'] }}</span>
                                                                    @if($starter['injury_status'] && $starter['injury_status'] !== 'Healthy')
                                                                        <span class="text-xs text-red-500 font-medium">{{ $starter['injury_status'] }}
                                                                            @if($starter['injury_body_part'])
                                                                                ({{ $starter['injury_body_part'] }})
                                                                            @endif
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                                <div class="flex flex-col items-end text-muted-foreground">
                                                                    <span>{{ $starter['position'] ?? 'N/A' }} • {{ $starter['team'] ?? 'FA' }}</span>
                                                                    @if($starter['bye_week'] && $details['current_week'] && $starter['bye_week'] == $details['current_week'])
                                                                        <span class="text-xs text-orange-500 font-medium">BYE</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <div class="flex justify-between items-center text-xs text-muted-foreground">
                                                                <span>
                                                                    @if($starter['adp_formatted'])
                                                                        ADP: {{ $starter['adp_formatted'] }}
                                                                    @elseif($starter['adp'])
                                                                        ADP: {{ number_format($starter['adp'], 1) }}
                                                                    @endif
                                                                </span>
                                                                <div class="flex gap-2">
                                                                    @if($starter['adds_24h'] !== null && $starter['adds_24h'] > 0)
                                                                        <span class="text-green-600">+{{ $starter['adds_24h'] }}</span>
                                                                    @endif
                                                                    @if($starter['drops_24h'] !== null && $starter['drops_24h'] > 0)
                                                                        <span class="text-red-600">−{{ $starter['drops_24h'] }}</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Opponent Starters -->
                                    @if (!empty($userMatchup['opponent_starters']))
                                        <div class="mt-3 pt-2 border-t">
                                            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide mb-1">Opponent Starters</p>
                                            <div class="space-y-1">
                                                @foreach ($userMatchup['opponent_starters'] as $starter)
                                                    @if ($starter && isset($starter['first_name']))
                                                        <div class="space-y-1">
                                                            <div class="flex justify-between items-center text-xs">
                                                                <div class="flex flex-col">
                                                                    <span>{{ $starter['first_name'] }} {{ $starter['last_name'] }}</span>
                                                                    @if($starter['injury_status'] && $starter['injury_status'] !== 'Healthy')
                                                                        <span class="text-xs text-red-500 font-medium">{{ $starter['injury_status'] }}
                                                                            @if($starter['injury_body_part'])
                                                                                ({{ $starter['injury_body_part'] }})
                                                                            @endif
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                                <div class="flex flex-col items-end text-muted-foreground">
                                                                    <span>{{ $starter['position'] ?? 'N/A' }} • {{ $starter['team'] ?? 'FA' }}</span>
                                                                    @if($starter['bye_week'] && $details['current_week'] && $starter['bye_week'] == $details['current_week'])
                                                                        <span class="text-xs text-orange-500 font-medium">BYE</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <div class="flex justify-between items-center text-xs text-muted-foreground">
                                                                <span>
                                                                    @if($starter['adp_formatted'])
                                                                        ADP: {{ $starter['adp_formatted'] }}
                                                                    @elseif($starter['adp'])
                                                                        ADP: {{ number_format($starter['adp'], 1) }}
                                                                    @endif
                                                                </span>
                                                                <div class="flex gap-2">
                                                                    @if($starter['adds_24h'] !== null && $starter['adds_24h'] > 0)
                                                                        <span class="text-green-600">+{{ $starter['adds_24h'] }}</span>
                                                                    @endif
                                                                    @if($starter['drops_24h'] !== null && $starter['drops_24h'] > 0)
                                                                        <span class="text-red-600">−{{ $starter['drops_24h'] }}</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="text-sm text-muted-foreground">
                                No matchup data available for Week {{ $details['current_week'] }}
                            </div>
                        @endif

                        @if (isset($details['error']))
                            <flux:callout variant="danger" class="mt-3">
                                ⚠️ Failed to load league details: {{ $details['error'] }}
                            </flux:callout>
                        @endif
                    </div>
                </flux:callout>
            @endforeach
        </div>
    @endif
</div>
