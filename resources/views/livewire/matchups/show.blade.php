<?php

use App\Actions\Matchups\AssembleMatchupViewModel;
use App\Actions\Matchups\DetermineCurrentWeek;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

new class extends Component
{
    public int|string $leagueId;
    public ?int $week = null;
    public ?int $rosterId = null;

    public function mount(string $leagueId, ?int $week = null, ?int $rosterId = null): void
    {
        $this->leagueId = $leagueId;
        $this->week = $week;
        $this->rosterId = $rosterId;
    }

    public function getModelProperty(): array
    {
        $roster = $this->rosterId !== null ? (int) $this->rosterId : 0;
        return app(AssembleMatchupViewModel::class)->execute((string) $this->leagueId, $this->week, $roster);
    }

    public function refreshMatchup(): void
    {
        // Determine the current week if not specified
        $currentWeek = $this->week ?? app(DetermineCurrentWeek::class)->execute('nfl')['week'];

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
                                <div class="text-2xl font-bold">{{ number_format($this->model['home']['totals']['total_estimated'], 1) }}</div>
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
                                <div class="text-2xl font-bold">{{ number_format($this->model['away']['totals']['total_estimated'], 1) }}</div>
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
                                @php $row = $this->model['home']['points'][$pid] ?? null; @endphp
                                <div class="py-2 flex items-center justify-between text-sm">
                                    <div class="text-muted-foreground">{{ $pid }}</div>
                                    @if ($row)
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs {{ $row['status']==='locked' ? 'text-gray-500' : 'text-emerald-600' }}">{{ ucfirst($row['status']) }}</span>
                                            <span class="font-medium">{{ number_format($row['used'], 1) }}</span>
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
                                @php $row = $this->model['away']['points'][$pid] ?? null; @endphp
                                <div class="py-2 flex items-center justify-between text-sm">
                                    <div class="text-muted-foreground">{{ $pid }}</div>
                                    @if ($row)
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs {{ $row['status']==='locked' ? 'text-gray-500' : 'text-emerald-600' }}">{{ ucfirst($row['status']) }}</span>
                                            <span class="font-medium">{{ number_format($row['used'], 1) }}</span>
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
                        @if (!empty($this->model['roster_options']))
                            <div class="mt-2">
                                <label class="text-xs text-muted-foreground">Select roster</label>
                                <select class="mt-1 block w-full rounded border bg-background p-2" wire:model="rosterId">
                                    @foreach ($this->model['roster_options'] as $opt)
                                        <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                </flux:callout>
            </div>
        </div>
    @endif
</section>
