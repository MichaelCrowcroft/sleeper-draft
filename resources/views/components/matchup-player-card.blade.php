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

                    <!-- Risk Profile -->
                    @if(isset($player['risk_profile']) && $player['risk_profile'])
                        @php
                            // Flux Free common variants: primary, secondary, destructive, outline
                            $variant = match($player['risk_profile']) {
                                'safe' => 'primary',
                                'balanced' => 'secondary',
                                'volatile' => 'destructive',
                                default => 'secondary'
                            };
                            $label = ucfirst($player['risk_profile']);
                        @endphp
                        <flux:badge variant="{{ $variant }}" size="sm" class="shrink-0">{{ $label }}</flux:badge>
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
            if(!$hasProjected && isset($player['projection']['pts_ppr']) && $player['projection']['pts_ppr'] !== null) { $hasProjected = true; }
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
                {{ number_format(($player['projection']['stats']['pts_ppr'] ?? $player['projection']['pts_ppr']), 1) }}
            </div>
            <div class="text-xs text-muted-foreground">Projected</div>
            @if(isset($player['projected_range_90']))
                <div class="text-[11px] text-muted-foreground mt-1">
                    90%: {{ number_format($player['projected_range_90']['lower_90'], 1) }}–{{ number_format($player['projected_range_90']['upper_90'], 1) }}
                </div>
            @endif
        @else
            <div class="text-muted-foreground">-</div>
            <div class="text-xs text-muted-foreground">No Data</div>
        @endif
    </div>
</div>
