@props([
    'player',
    'isBench' => false
])

<div class="flex items-center justify-between p-3 rounded-lg {{ $isBench ? 'bg-muted/30 border border-muted' : 'bg-background border' }} hover:bg-muted/20 transition-colors">
    <div class="flex items-center gap-3 flex-1 min-w-0">
        <!-- Position Badge -->
        <flux:badge
            variant="{{ $isBench ? 'outline' : 'secondary' }}"
            size="sm"
            class="shrink-0"
        >
            {{ $player['position'] }}
        </flux:badge>

        <!-- Player Info -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <h4 class="font-medium text-sm truncate">{{ $player['name'] }}</h4>
                <flux:badge variant="outline" size="sm" class="shrink-0">
                    {{ $player['team'] }}
                </flux:badge>

                <!-- Injury Status -->
                @if($player['injury_status'] && $player['injury_status'] !== 'Healthy')
                    <flux:badge variant="destructive" size="sm" class="shrink-0">
                        {{ $player['injury_status'] }}
                    </flux:badge>
                @endif
            </div>

            @if($player['injury_status'] && $player['injury_status'] !== 'Healthy')
                <p class="text-xs text-muted-foreground mt-1">Injured</p>
            @endif
        </div>
    </div>

    <!-- Projected Points -->
    <div class="text-right shrink-0">
        @if(isset($player['projection']['stats']['pts_ppr']) && $player['projection']['stats']['pts_ppr'] !== null)
            <div class="font-semibold text-green-600">
                {{ number_format($player['projection']['stats']['pts_ppr'], 1) }}
            </div>
            <div class="text-xs text-muted-foreground">Proj</div>
        @elseif(isset($player['stats']['stats']['pts_ppr']) && $player['stats']['stats']['pts_ppr'] !== null)
            <div class="font-semibold text-blue-600">
                {{ number_format($player['stats']['stats']['pts_ppr'], 1) }}
            </div>
            <div class="text-xs text-muted-foreground">Actual</div>
        @else
            <div class="text-muted-foreground">-</div>
            <div class="text-xs text-muted-foreground">Proj</div>
        @endif
    </div>
</div>
