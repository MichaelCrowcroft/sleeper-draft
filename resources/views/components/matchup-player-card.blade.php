@props([
    'player',
    'isBench' => false,
    'slotLabel' => null,
])

<div class="flex items-center justify-between p-3 rounded-lg {{ $isBench ? 'bg-muted/30 border border-muted' : 'bg-background border' }} hover:bg-muted/20 transition-colors">
    <div class="flex items-center gap-3 flex-1 min-w-0">
        <!-- Position / Slot Badge -->
        <div class="flex items-center gap-1 shrink-0">
            <flux:badge
                variant="{{ $isBench ? 'outline' : 'secondary' }}"
                size="sm"
                class="shrink-0"
            >
                {{ $player['position'] }}
            </flux:badge>
            @if(!$isBench && $slotLabel)
                <flux:badge variant="outline" size="sm" class="shrink-0">{{ $slotLabel }}</flux:badge>
            @endif
        </div>

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

    <!-- Points Display -->
    <div class="text-right shrink-0">
        @php
            $hasActual = isset($player['stats']['stats']['pts_ppr']) && $player['stats']['stats']['pts_ppr'] !== null;
            $hasProjected = isset($player['projection']['stats']['pts_ppr']) && $player['projection']['stats']['pts_ppr'] !== null;
        @endphp

        @if($hasActual)
            <div class="font-semibold text-blue-600">
                {{ number_format($player['stats']['stats']['pts_ppr'], 1) }}
            </div>
            <div class="text-xs text-muted-foreground">Actual</div>
            @if($hasProjected)
                <div class="text-xs text-muted-foreground mt-1">
                    (Proj: {{ number_format($player['projection']['stats']['pts_ppr'], 1) }})
                </div>
            @endif
        @elseif($hasProjected)
            <div class="font-semibold text-green-600">
                {{ number_format($player['projection']['stats']['pts_ppr'], 1) }}
            </div>
            <div class="text-xs text-muted-foreground">Projected</div>
        @else
            <div class="text-muted-foreground">-</div>
            <div class="text-xs text-muted-foreground">No Data</div>
        @endif
    </div>
</div>
