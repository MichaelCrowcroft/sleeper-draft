<?php

use App\Actions\Matchups\AssembleMatchupViewModel;
use App\Actions\Sleeper\GetSeasonState;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

new class extends Component
{
    public int|string $leagueId;
    public ?int $week = null;

    public function mount(string $leagueId, ?int $week = null): void
    {
        $this->leagueId = $leagueId;
        $this->week = $week;
    }

    public function getModelProperty(): array
    {
        return app(AssembleMatchupViewModel::class)->execute((string) $this->leagueId, $this->week, null);
    }

    public function refreshMatchup(): void
    {
        // Determine the current week if not specified
        $currentWeek = $this->week ?? app(GetSeasonState::class)->execute('nfl')['week'];

        // Clear all relevant cache keys
        $cacheKeys = [
            'sleeper:league:' . $this->leagueId,
            'sleeper:rosters:' . $this->leagueId,
            'sleeper:league_users:' . $this->leagueId,
            'sleeper:matchups:' . $this->leagueId . ':week:' . $currentWeek,
            'sleeper:state:current:nfl',
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Clear cached model property and refresh
        unset($this->model);
    }
}; ?>

<section class="w-full" wire:poll.30s>
    <div class="flex items-center justify-between mb-4">
        <div>
            <flux:heading size="xl">Matchup</flux:heading>
            <p class="text-muted-foreground">Week {{ $this->model['week'] }} • {{ $this->model['league']['name'] ?? 'League' }}</p>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="refreshMatchup" variant="ghost" size="sm" wire:loading.attr="disabled" wire:target="refreshMatchup">
                <span wire:loading.remove wire:target="refreshMatchup">Refresh</span>
                <span wire:loading wire:target="refreshMatchup">Refreshing...</span>
            </flux:button>
        </div>
    </div>

    @if (isset($this->model['error']))
        <flux:callout variant="danger">{{ $this->model['error'] }}</flux:callout>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:callout>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-semibold">Your Team</div>
                                <div class="text-sm text-muted-foreground">{{ $this->model['home']['owner_name'] ?? ('Roster '.$this->model['home']['roster_id']) }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold">{{ $this->model['home']['totals']['range']['display'] }}</div>
                                <div class="text-xs text-muted-foreground">Range: {{ number_format($this->model['home']['totals']['range']['min'], 1) }}-{{ number_format($this->model['home']['totals']['range']['max'], 1) }}</div>
                                <div class="text-xs text-muted-foreground">Actual {{ number_format($this->model['home']['totals']['actual'], 1) }} + Remaining {{ number_format($this->model['home']['totals']['projected_remaining'], 1) }}</div>
                            </div>
                        </div>
                    </flux:callout>

                    <flux:callout>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-semibold">Opponent</div>
                                <div class="text-sm text-muted-foreground">{{ $this->model['away']['owner_name'] ?? ('Roster '.$this->model['away']['roster_id']) }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold">{{ $this->model['away']['totals']['range']['display'] }}</div>
                                <div class="text-xs text-muted-foreground">Range: {{ number_format($this->model['away']['totals']['range']['min'], 1) }}-{{ number_format($this->model['away']['totals']['range']['max'], 1) }}</div>
                                <div class="text-xs text-muted-foreground">Actual {{ number_format($this->model['away']['totals']['actual'], 1) }} + Remaining {{ number_format($this->model['away']['totals']['projected_remaining'], 1) }}</div>
                            </div>
                        </div>
                    </flux:callout>
                </div>

                <div class="p-4 rounded-lg border bg-card">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-semibold">Win Probability</div>
                        <div class="text-sm text-muted-foreground">{{ (int) round($this->model['win_probability']['home'] * 100) }}% vs {{ (int) round($this->model['win_probability']['away'] * 100) }}%</div>
                    </div>
                    @php
                        $homePct = max(0, min(100, (int) round($this->model['win_probability']['home'] * 100)));
                    @endphp
                    <svg viewBox="0 0 100 6" preserveAspectRatio="none" class="w-full h-3 rounded overflow-hidden">
                        <rect x="0" y="0" width="100" height="6" class="fill-gray-200 dark:fill-gray-800" />
                        <rect x="0" y="0" width="{{ $homePct }}" height="6" class="fill-emerald-600" />
                    </svg>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <flux:heading size="md" class="mb-2">Your Starters</flux:heading>
                        <div class="divide-y">
                            @foreach ($this->model['home']['starters'] as $pid)
                                @php
                                    $row = $this->model['home']['starter_points'][$pid] ?? null;
                                    $player = $this->model['players'][$pid] ?? null;
                                @endphp
                                <div class="py-2 flex items-center justify-between text-sm">
                                    <div>
                                        <div class="font-medium">{{ $player['name'] ?? $pid }}</div>
                                        @if ($player && ($player['position'] || $player['team']))
                                            <div class="text-xs text-muted-foreground">
                                                {{ $player['position'] }}{{ $player['position'] && $player['team'] ? ' • ' : '' }}{{ $player['team'] }}
                                            </div>
                                        @endif
                                    </div>
                                    @if ($row)
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs px-2 py-1 rounded-full text-white
                                                @if($row['risk'] === 'safe') bg-green-600
                                                @elseif($row['risk'] === 'low') bg-blue-600
                                                @elseif($row['risk'] === 'medium') bg-yellow-600
                                                @else bg-red-600
                                                @endif">
                                                @if($row['status'] === 'locked') ✓ Locked in @else ⚠ {{ ucfirst($row['risk']) }} @endif
                                            </span>
                                            <span class="font-medium">{{ $row['range']['display'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <flux:heading size="md" class="mb-2">Opponent Starters</flux:heading>
                        <div class="divide-y">
                            @foreach ($this->model['away']['starters'] as $pid)
                                @php
                                    $row = $this->model['away']['starter_points'][$pid] ?? null;
                                    $player = $this->model['players'][$pid] ?? null;
                                @endphp
                                <div class="py-2 flex items-center justify-between text-sm">
                                    <div>
                                        <div class="font-medium">{{ $player['name'] ?? $pid }}</div>
                                        @if ($player && ($player['position'] || $player['team']))
                                            <div class="text-xs text-muted-foreground">
                                                {{ $player['position'] }}{{ $player['position'] && $player['team'] ? ' • ' : '' }}{{ $player['team'] }}
                                            </div>
                                        @endif
                                    </div>
                                    @if ($row)
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs px-2 py-1 rounded-full text-white
                                                @if($row['risk'] === 'safe') bg-green-600
                                                @elseif($row['risk'] === 'low') bg-blue-600
                                                @elseif($row['risk'] === 'medium') bg-yellow-600
                                                @else bg-red-600
                                                @endif">
                                                @if($row['status'] === 'locked') ✓ Locked in @else ⚠ {{ ucfirst($row['risk']) }} @endif
                                            </span>
                                            <span class="font-medium">{{ $row['range']['display'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Bench Players Section -->
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <flux:heading size="md" class="mb-2">Your Bench</flux:heading>
                        <div class="divide-y">
                            @foreach ($this->model['home']['bench'] as $pid)
                                @php
                                    $row = $this->model['home']['bench_points'][$pid] ?? null;
                                    $player = $this->model['players'][$pid] ?? null;
                                @endphp
                                <div class="py-2 flex items-center justify-between text-sm">
                                    <div>
                                        <div class="font-medium">{{ $player['name'] ?? $pid }}</div>
                                        @if ($player && ($player['position'] || $player['team']))
                                            <div class="text-xs text-muted-foreground">
                                                {{ $player['position'] }}{{ $player['position'] && $player['team'] ? ' • ' : '' }}{{ $player['team'] }}
                                            </div>
                                        @endif
                                    </div>
                                    @if ($row)
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">{{ $row['range']['display'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <flux:heading size="md" class="mb-2">Opponent Bench</flux:heading>
                        <div class="divide-y">
                            @foreach ($this->model['away']['bench'] as $pid)
                                @php
                                    $row = $this->model['away']['bench_points'][$pid] ?? null;
                                    $player = $this->model['players'][$pid] ?? null;
                                @endphp
                                <div class="py-2 flex items-center justify-between text-sm">
                                    <div>
                                        <div class="font-medium">{{ $player['name'] ?? $pid }}</div>
                                        @if ($player && ($player['position'] || $player['team']))
                                            <div class="text-xs text-muted-foreground">
                                                {{ $player['position'] }}{{ $player['position'] && $player['team'] ? ' • ' : '' }}{{ $player['team'] }}
                                            </div>
                                        @endif
                                    </div>
                                    @if ($row)
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">{{ $row['range']['display'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <flux:callout>
                    <div class="space-y-2 text-sm">
                        <div><span class="font-medium">League:</span> {{ $this->model['league']['name'] ?? 'N/A' }} ({{ $this->model['league']['id'] }})</div>
                        <div><span class="font-medium">Season:</span> {{ $this->model['season'] }}</div>
                        <div><span class="font-medium">Week:</span> {{ $this->model['week'] }}</div>
                    </div>
                </flux:callout>

                <!-- Week Navigation -->
                <flux:callout>
                    <div class="space-y-2">
                        <div class="text-sm font-medium">Navigate to Week</div>
                        <div class="grid grid-cols-6 gap-1">
                            @for ($i = 1; $i <= 18; $i++)
                                @php
                                    $isCurrentWeek = $i === $this->model['week'];
                                    $routeName = $i === app(GetSeasonState::class)->execute('nfl')['week'] ? 'matchups.show.current' : 'matchups.show';
                                    $routeParams = $i === app(GetSeasonState::class)->execute('nfl')['week']
                                        ? ['leagueId' => $this->leagueId]
                                        : ['leagueId' => $this->leagueId, 'week' => $i];
                                @endphp
                                <flux:button
                                    :href="route($routeName, $routeParams)"
                                    wire:navigate
                                    variant="{{ $isCurrentWeek ? 'primary' : 'ghost' }}"
                                    size="sm"
                                    class="text-xs px-2 py-1 h-8 {{ $isCurrentWeek ? 'pointer-events-none' : '' }}"
                                >
                                    {{ $i }}
                                </flux:button>
                            @endfor
                        </div>
                    </div>
                </flux:callout>
            </div>
        </div>
    @endif
</section>
