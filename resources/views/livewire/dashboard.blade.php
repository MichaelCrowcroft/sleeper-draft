<?php

use App\Models\Player;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;

new class extends Component
{
    public function getStatsProperty()
    {
        return Cache::remember('dashboard_stats', now()->addMinutes(30), function () {
            $totalPlayers = Player::where('active', true)->count();
            $injuredPlayers = Player::where('active', true)
                ->whereNotNull('injury_status')
                ->where('injury_status', '!=', 'Healthy')
                ->count();

            $positionCounts = Player::where('active', true)
                ->whereIn('position', ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'])
                ->selectRaw('position, COUNT(*) as count')
                ->groupBy('position')
                ->pluck('count', 'position')
                ->toArray();

            $teamCount = Player::where('active', true)
                ->whereNotNull('team')
                ->distinct('team')
                ->count();

            $trendingAdds = Player::where('active', true)
                ->whereNotNull('adds_24h')
                ->where('adds_24h', '>', 0)
                ->count();

            $trendingDrops = Player::where('active', true)
                ->whereNotNull('drops_24h')
                ->where('drops_24h', '>', 0)
                ->count();

            return [
                'total_players' => $totalPlayers,
                'injured_players' => $injuredPlayers,
                'position_counts' => $positionCounts,
                'team_count' => $teamCount,
                'trending_adds' => $trendingAdds,
                'trending_drops' => $trendingDrops,
            ];
        });
    }

    public function getTopTrendingAddsProperty()
    {
        return Player::where('active', true)
            ->whereNotNull('adds_24h')
            ->where('adds_24h', '>', 0)
            ->orderBy('adds_24h', 'desc')
            ->limit(5)
            ->get();
    }

    public function getTopTrendingDropsProperty()
    {
        return Player::where('active', true)
            ->whereNotNull('drops_24h')
            ->where('drops_24h', '>', 0)
            ->orderBy('drops_24h', 'desc')
            ->limit(5)
            ->get();
    }

    public function getResolvedWeekProperty()
    {
        try {
            $resp = \MichaelCrowcroft\SleeperLaravel\Facades\Sleeper::state()->current('nfl');
            if ($resp->successful()) {
                $state = $resp->json();
                $w = isset($state['week']) ? (int) $state['week'] : null;

                return ($w && $w >= 1 && $w <= 18) ? $w : null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}; ?>

<section class="w-full">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Dashboard</flux:heading>
                <p class="text-muted-foreground mt-1">Welcome to your fantasy football command center</p>
            </div>
            @if (Auth::check())
                <div class="text-right">
                    <flux:heading size="sm">Welcome back, {{ Auth::user()->name }}</flux:heading>
                    @if ($this->resolvedWeek)
                        <p class="text-muted-foreground text-sm">NFL Week {{ $this->resolvedWeek }}</p>
                    @endif
                </div>
            @endif
        </div>

        @if ($this->resolvedWeek)
            <flux:callout variant="info">
                <div class="flex items-center gap-2">
                    <flux:icon name="calendar" class="h-4 w-4" />
                    <span>Currently tracking NFL Week {{ $this->resolvedWeek }}</span>
                </div>
            </flux:callout>
        @endif

        <!-- Key Statistics -->
        <div>
            <flux:heading size="lg" class="mb-4">System Overview</flux:heading>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <flux:callout>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($this->stats['total_players']) }}</div>
                        <div class="text-sm text-muted-foreground">Active Players</div>
                    </div>
                </flux:callout>

                <flux:callout>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-orange-600">{{ $this->stats['injured_players'] }}</div>
                        <div class="text-sm text-muted-foreground">Injured Players</div>
                    </div>
                </flux:callout>

                <flux:callout>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-600">{{ $this->stats['team_count'] }}</div>
                        <div class="text-sm text-muted-foreground">NFL Teams</div>
                    </div>
                </flux:callout>

                <flux:callout>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ count($this->stats['position_counts']) }}</div>
                        <div class="text-sm text-muted-foreground">Positions</div>
                    </div>
                </flux:callout>
            </div>
        </div>

        <!-- Position Breakdown -->
        @if (!empty($this->stats['position_counts']))
            <div>
                <flux:heading size="lg" class="mb-4">Players by Position</flux:heading>
                <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-6">
                    @foreach ($this->stats['position_counts'] as $position => $count)
                        <flux:callout>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $count }}</div>
                                <div class="text-sm text-muted-foreground">{{ $position }}</div>
                            </div>
                        </flux:callout>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Trending Section -->
        @if ($this->stats['trending_adds'] > 0 || $this->stats['trending_drops'] > 0)
            <div>
                <flux:heading size="lg" class="mb-4">Trending Activity</flux:heading>
                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Trending Adds -->
                    @if ($this->topTrendingAdds->count() > 0)
                        <flux:callout>
                            <flux:heading size="md" class="mb-3 flex items-center gap-2">
                                <flux:icon name="chevrons-up-down" class="h-5 w-5 text-emerald-600" />
                                Top Trending Adds ({{ $this->stats['trending_adds'] }} total)
                            </flux:heading>
                            <div class="space-y-2">
                                @foreach ($this->topTrendingAdds as $player)
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">{{ $player->first_name }} {{ $player->last_name }}</span>
                                            <flux:badge variant="secondary" size="sm">{{ $player->position }}</flux:badge>
                                            <flux:badge variant="outline" size="sm">{{ $player->team }}</flux:badge>
                                        </div>
                                        <span class="text-emerald-600 font-semibold">+{{ number_format($player->adds_24h) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </flux:callout>
                    @endif

                    <!-- Trending Drops -->
                    @if ($this->topTrendingDrops->count() > 0)
                        <flux:callout>
                            <flux:heading size="md" class="mb-3 flex items-center gap-2">
                                <flux:icon name="chevrons-up-down" class="h-5 w-5 text-red-600" />
                                Top Trending Drops ({{ $this->stats['trending_drops'] }} total)
                            </flux:heading>
                            <div class="space-y-2">
                                @foreach ($this->topTrendingDrops as $player)
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">{{ $player->first_name }} {{ $player->last_name }}</span>
                                            <flux:badge variant="secondary" size="sm">{{ $player->position }}</flux:badge>
                                            <flux:badge variant="outline" size="sm">{{ $player->team }}</flux:badge>
                                        </div>
                                        <span class="text-red-600 font-semibold">-{{ number_format($player->drops_24h) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </flux:callout>
                    @endif
                </div>
            </div>
        @endif

        <!-- Quick Actions -->
        <div>
            <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <flux:callout>
                    <div class="text-center space-y-3">
                        <flux:icon name="user-group" class="h-8 w-8 mx-auto text-emerald-600" />
                        <div>
                            <div class="font-semibold">Browse Players</div>
                            <div class="text-sm text-muted-foreground">Search and filter all players</div>
                        </div>
                        <flux:button
                            variant="primary"
                            size="sm"
                            href="{{ route('players.index') }}"
                            wire:navigate
                        >
                            View Players
                        </flux:button>
                    </div>
                </flux:callout>

                <flux:callout>
                    <div class="text-center space-y-3">
                        <flux:icon name="chart-bar" class="h-8 w-8 mx-auto text-emerald-600" />
                        <div>
                            <div class="font-semibold">Analytics</div>
                            <div class="text-sm text-muted-foreground">View detailed analytics</div>
                        </div>
                        <flux:button
                            variant="primary"
                            size="sm"
                            href="{{ route('analytics.index') }}"
                            wire:navigate
                        >
                            View Analytics
                        </flux:button>
                    </div>
                </flux:callout>

                <flux:callout>
                    <div class="text-center space-y-3">
                        <flux:icon name="chat-bubble-left-ellipsis" class="h-8 w-8 mx-auto text-emerald-600" />
                        <div>
                            <div class="font-semibold">AI Commissioner</div>
                            <div class="text-sm text-muted-foreground">Generate league summaries with AI</div>
                        </div>
                        <flux:button
                            variant="primary"
                            size="sm"
                            href="{{ route('chat') }}"
                            wire:navigate
                        >
                            Generate Summary
                        </flux:button>
                    </div>
                </flux:callout>

                <flux:callout>
                    <div class="text-center space-y-3">
                        <flux:icon name="cog-8-tooth" class="h-8 w-8 mx-auto text-emerald-600" />
                        <div>
                            <div class="font-semibold">Settings</div>
                            <div class="text-sm text-muted-foreground">Manage your account</div>
                        </div>
                        <flux:button
                            variant="primary"
                            size="sm"
                            href="{{ route('settings.profile') }}"
                            wire:navigate
                        >
                            Go to Settings
                        </flux:button>
                    </div>
                </flux:callout>
            </div>
        </div>
    </div>
</section>
