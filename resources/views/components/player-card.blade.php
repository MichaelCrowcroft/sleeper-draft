@props([
    'player',
    'leagueStatus' => null,
    'showDetailed' => false
])

<div class="bg-card rounded-lg border p-4 hover:shadow-md transition-shadow">
    <div class="flex items-start justify-between mb-3">
        <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
                <h3 class="font-semibold text-lg">{{ $player->first_name }} {{ $player->last_name }}</h3>
                <flux:badge variant="secondary" size="sm">{{ $player->position }}</flux:badge>
                <flux:badge variant="outline" size="sm">{{ $player->team }}</flux:badge>
            </div>

            @if ($player->age)
                <p class="text-sm text-muted-foreground">{{ $player->age }} years old</p>
            @endif
        </div>

        @if ($leagueStatus && $leagueStatus['status'] === 'owned')
            <flux:badge variant="default" size="sm">{{ $leagueStatus['team_name'] }}</flux:badge>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-4 mb-3">
        <!-- ADP & Projections -->
        <div class="space-y-2">
            <div class="flex justify-between items-center">
                <span class="text-sm font-medium">ADP</span>
                @if ($player->adp_formatted)
                    <span class="text-sm font-mono">{{ $player->adp_formatted }}</span>
                @elseif ($player->adp)
                    <span class="text-sm font-mono">{{ number_format($player->adp, 1) }}</span>
                @else
                    <span class="text-sm text-muted-foreground">-</span>
                @endif
            </div>

            @if ($showDetailed && $player->recent_stats)
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium">Last Week</span>
                    <span class="text-sm font-mono">{{ $player->recent_stats }}</span>
                </div>
            @endif
        </div>

        <!-- Injury Status -->
        <div class="space-y-2">
            <div class="flex justify-between items-center">
                <span class="text-sm font-medium">Status</span>
                @if ($player->injury_status && $player->injury_status !== 'Healthy')
                    <span class="text-sm font-medium text-red-600">{{ $player->injury_status }}</span>
                @else
                    <span class="text-sm text-green-600">Healthy</span>
                @endif
            </div>

            @if ($player->injury_body_part && $player->injury_status !== 'Healthy')
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium">Injury</span>
                    <span class="text-sm text-muted-foreground">{{ $player->injury_body_part }}</span>
                </div>
            @endif
        </div>
    </div>

    @if ($showDetailed)
        <div class="flex gap-2 pt-3 border-t">
            <flux:button variant="outline" size="sm">Watch</flux:button>
            <flux:button variant="outline" size="sm">Compare</flux:button>
            <flux:button variant="outline" size="sm">Details</flux:button>
        </div>
    @endif
</div>
