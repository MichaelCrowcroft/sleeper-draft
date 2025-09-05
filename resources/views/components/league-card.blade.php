@props([
    'leagueId',
    'league',
    'rosters' => [],
    'matchups' => [],
    'currentWeek' => null,
    'userMatchup' => null,
    'userRoster' => null,
    'error' => null
])

<div class="bg-card rounded-lg border p-6 hover:shadow-lg transition-shadow">
    <!-- League Header -->
    <div class="flex items-start justify-between mb-4">
        <div>
            <h3 class="font-semibold text-lg">{{ $league['name'] }}</h3>
            <p class="text-sm text-muted-foreground">ID: {{ $leagueId }}</p>
        </div>
        <flux:badge variant="outline" size="sm">
            Week {{ $currentWeek ?? '?' }}
        </flux:badge>
    </div>

    @if ($error)
        <flux:callout variant="danger" class="mb-4">
            ⚠️ {{ $error }}
        </flux:callout>
    @endif

    <!-- User Team Overview -->
    @if ($userRoster)
        <div class="mb-4">
            <div class="flex items-center justify-between mb-2">
                <h4 class="font-medium text-sm">{{ $userRoster['owner']['team_name'] ?? 'Your Team' }}</h4>
                <flux:badge variant="secondary" size="sm">
                    {{ count($userRoster['starters'] ?? []) }} starters
                </flux:badge>
            </div>

            <!-- Quick Roster Summary -->
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-muted-foreground">QB:</span>
                    <span class="font-medium">{{ collect($userRoster['starters'] ?? [])->filter(fn($p) => str_starts_with($p, 'QB'))->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">RB:</span>
                    <span class="font-medium">{{ collect($userRoster['starters'] ?? [])->filter(fn($p) => str_starts_with($p, 'RB'))->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">WR:</span>
                    <span class="font-medium">{{ collect($userRoster['starters'] ?? [])->filter(fn($p) => str_starts_with($p, 'WR'))->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">TE:</span>
                    <span class="font-medium">{{ collect($userRoster['starters'] ?? [])->filter(fn($p) => str_starts_with($p, 'TE'))->count() }}</span>
                </div>
            </div>
        </div>
    @endif

    <!-- Current Matchup -->
    @if ($userMatchup)
        <div class="mb-4">
            <h4 class="font-medium text-sm mb-2">This Week's Matchup</h4>

            <div class="bg-muted/50 rounded-lg p-3">
                <!-- Score Display -->
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium">{{ $userMatchup['team_name'] ?? 'Your Team' }}</span>
                    <span class="text-lg font-bold">{{ $userMatchup['points'] ?? 0 }}</span>
                </div>

                <div class="text-center text-xs text-muted-foreground mb-2">vs</div>

                <div class="flex justify-between items-center mb-3">
                    <span class="text-sm font-medium">{{ $userMatchup['opponent_details']['team_name'] ?? 'Opponent' }}</span>
                    <span class="text-lg font-bold">{{ $userMatchup['opponent_details']['points'] ?? '?' }}</span>
                </div>

                <!-- Projected Finish -->
                @if (isset($userMatchup['detailed_info']))
                    <div class="border-t pt-2">
                        <div class="flex justify-between text-xs">
                            <span class="text-muted-foreground">Projected:</span>
                            <span class="font-medium">
                                {{ number_format($userMatchup['detailed_info']['user_total_projected'] ?? 0, 1) }} -
                                {{ number_format($userMatchup['detailed_info']['opponent_total_projected'] ?? 0, 1) }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Quick Actions -->
    <div class="flex gap-2">
        <flux:button variant="outline" size="sm" class="flex-1">
            View League
        </flux:button>
        <flux:button variant="outline" size="sm" class="flex-1">
            Trade Block
        </flux:button>
    </div>
</div>
