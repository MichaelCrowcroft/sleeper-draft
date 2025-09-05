<x-layouts.app :title="$player->first_name . ' ' . $player->last_name">
    <div class="space-y-6">
        <!-- Back Button -->
        <flux:button variant="ghost" href="{{ route('players.index') }}">
            ← Back to Players
        </flux:button>

        <!-- Player Header -->
        <div class="rounded-lg border bg-card p-6">
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ $player->first_name }} {{ $player->last_name }}</flux:heading>
                    <div class="flex items-center gap-4 text-sm text-muted-foreground">
                        <flux:badge variant="secondary">{{ $player->position }}</flux:badge>
                        <flux:badge variant="outline">{{ $player->team }}</flux:badge>
                        @if ($player->age)
                            <span>{{ $player->age }} years old</span>
                        @endif
                        @if ($player->height)
                            <span>{{ $player->height }}</span>
                        @endif
                        @if ($player->weight)
                            <span>{{ $player->weight }} lbs</span>
                        @endif
                    </div>
                    <div class="text-sm text-muted-foreground">
                        @if ($player->college)
                            <span>College: {{ $player->college }}</span>
                        @endif
                    </div>
                </div>

                <div class="text-right space-y-2">
                    @if ($player->adp_formatted)
                        <div>
                            <div class="text-sm text-muted-foreground">ADP</div>
                            <div class="font-semibold">{{ $player->adp_formatted }}</div>
                        </div>
                    @elseif ($player->adp)
                        <div>
                            <div class="text-sm text-muted-foreground">ADP</div>
                            <div class="font-semibold">{{ number_format($player->adp, 1) }}</div>
                        </div>
                    @endif

                    @if ($player->injury_status && $player->injury_status !== 'Healthy')
                        <div>
                            <div class="text-sm text-muted-foreground">Injury Status</div>
                            <div class="font-semibold text-red-600">{{ $player->injury_status }}</div>
                            @if ($player->injury_body_part)
                                <div class="text-xs text-muted-foreground">{{ $player->injury_body_part }}</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Season Summary Cards -->
        <div class="grid gap-4 md:grid-cols-2">
            <!-- 2024 Season Stats -->
            @if (!empty($stats2024))
                <flux:callout>
                    <flux:heading size="md" class="mb-4">2024 Season Stats</flux:heading>
                    <div class="space-y-3">
                        @if (isset($summary2024['total_points']))
                            <div class="flex justify-between">
                                <span>Total Points:</span>
                                <span class="font-semibold">{{ number_format($summary2024['total_points'], 1) }}</span>
                            </div>
                        @endif
                        @if (isset($summary2024['games_active']))
                            <div class="flex justify-between">
                                <span>Games Played:</span>
                                <span class="font-semibold">{{ $summary2024['games_active'] }}</span>
                            </div>
                        @endif
                        @if (isset($summary2024['average_points_per_game']) && $summary2024['games_active'] > 0)
                            <div class="flex justify-between">
                                <span>Avg PPG:</span>
                                <span class="font-semibold">{{ number_format($summary2024['average_points_per_game'], 1) }}</span>
                            </div>
                        @endif
                        @if (isset($summary2024['max_points']))
                            <div class="flex justify-between">
                                <span>Best Game:</span>
                                <span class="font-semibold">{{ number_format($summary2024['max_points'], 1) }}</span>
                            </div>
                        @endif
                    </div>

                    <flux:separator class="my-4" />

                    <div class="text-sm text-muted-foreground">
                        <div class="grid grid-cols-2 gap-4">
                            @foreach (array_slice($stats2024, 0, 6) as $stat => $value)
                                <div class="flex justify-between">
                                    <span>{{ ucfirst(str_replace('_', ' ', $stat)) }}:</span>
                                    <span class="font-medium">{{ is_numeric($value) ? number_format($value, 1) : $value }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </flux:callout>
            @endif

            <!-- 2025 Projections -->
            @if (!empty($projections2025))
                <flux:callout>
                    <flux:heading size="md" class="mb-4">2025 Projections</flux:heading>
                    <div class="space-y-3">
                        @if (isset($projections2025['total_points']))
                            <div class="flex justify-between">
                                <span>Projected Points:</span>
                                <span class="font-semibold">{{ number_format($projections2025['total_points'], 1) }}</span>
                            </div>
                        @endif
                        @if (isset($projections2025['games']))
                            <div class="flex justify-between">
                                <span>Projected Games:</span>
                                <span class="font-semibold">{{ $projections2025['games'] }}</span>
                            </div>
                        @endif
                        @if (isset($projections2025['average_points_per_game']) && $projections2025['games'] > 0)
                            <div class="flex justify-between">
                                <span>Projected PPG:</span>
                                <span class="font-semibold">{{ number_format($projections2025['average_points_per_game'], 1) }}</span>
                            </div>
                        @endif
                        @if (isset($projections2025['max_points']))
                            <div class="flex justify-between">
                                <span>Best Game Projection:</span>
                                <span class="font-semibold">{{ number_format($projections2025['max_points'], 1) }}</span>
                            </div>
                        @endif
                    </div>

                    <flux:separator class="my-4" />

                    <div class="text-sm text-muted-foreground">
                        <div class="text-center">
                            <span>Projection Range: {{ number_format($projections2025['stddev_below'] ?? 0, 1) }} - {{ number_format($projections2025['stddev_above'] ?? 0, 1) }} points</span>
                        </div>
                    </div>
                </flux:callout>
            @endif
        </div>

        <!-- Weekly Breakdown Tabs -->
        <flux:callout>
            <flux:heading size="md" class="mb-4">Weekly Performance</flux:heading>

            <!-- Tab Navigation -->
            <div class="flex border-b mb-4">
                <button
                    class="px-4 py-2 border-b-2 font-medium text-sm {{ request('tab') !== 'projections' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground' }}"
                    onclick="showTab('stats')"
                >
                    2024 Stats
                </button>
                <button
                    class="px-4 py-2 border-b-2 font-medium text-sm {{ request('tab') === 'projections' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground' }}"
                    onclick="showTab('projections')"
                >
                    2025 Projections
                </button>
            </div>

            <!-- 2024 Stats Tab -->
            <div id="stats-tab" class="{{ request('tab') === 'projections' ? 'hidden' : '' }}">
                @if ($weeklyStats->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b">
                                <tr>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Week</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Points</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Pass Yds</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Pass TDs</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Rush Yds</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Rush TDs</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Rec Yds</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Rec TDs</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($weeklyStats->sortBy('week') as $weekStat)
                                    @php
                                        $stats = $weekStat->stats ?? [];
                                    @endphp
                                    <tr class="hover:bg-muted/50">
                                        <td class="px-3 py-2 text-sm font-medium">{{ $weekStat->week }}</td>
                                        <td class="px-3 py-2 text-sm">{{ isset($stats['pts_ppr']) ? number_format($stats['pts_ppr'], 1) : '-' }}</td>
                                        <td class="px-3 py-2 text-sm">{{ $stats['pass_yd'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-sm">{{ $stats['pass_td'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-sm">{{ $stats['rush_yd'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-sm">{{ $stats['rush_td'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-sm">{{ $stats['rec_yd'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-sm">{{ $stats['rec_td'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 text-muted-foreground">
                        No 2024 stats available for this player.
                    </div>
                @endif
            </div>

            <!-- 2025 Projections Tab -->
            <div id="projections-tab" class="{{ request('tab') !== 'projections' ? 'hidden' : '' }}">
                @if ($weeklyProjections->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b">
                                <tr>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Week</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Projected Points</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Pass Yds</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Pass TDs</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Rush Yds</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Rush TDs</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Rec Yds</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium">Rec TDs</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($weeklyProjections->sortBy('week') as $weekProjection)
                                    @php
                                        $stats = $weekProjection->stats ?? [];
                                    @endphp
                                    <tr class="hover:bg-muted/50">
                                        <td class="px-3 py-2 text-sm font-medium">{{ $weekProjection->week }}</td>
                                        <td class="px-3 py-2 text-sm">{{ isset($stats['pts_ppr']) ? number_format($stats['pts_ppr'], 1) : '-' }}</td>
                                        <td class="px-3 py-2 text-sm">{{ $stats['pass_yd'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-sm">{{ $stats['pass_td'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-sm">{{ $stats['rush_yd'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-sm">{{ $stats['rush_td'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-sm">{{ $stats['rec_yd'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-sm">{{ $stats['rec_td'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 text-muted-foreground">
                        No 2025 projections available for this player.
                    </div>
                @endif
            </div>
        </flux:callout>
    </div>

    <script>
        function showTab(tab) {
            const statsTab = document.getElementById('stats-tab');
            const projectionsTab = document.getElementById('projections-tab');
            const statsButton = document.querySelector('button[onclick="showTab(\'stats\')"]');
            const projectionsButton = document.querySelector('button[onclick="showTab(\'projections\')"]');

            if (tab === 'stats') {
                statsTab.classList.remove('hidden');
                projectionsTab.classList.add('hidden');
                statsButton.classList.add('border-primary', 'text-primary');
                statsButton.classList.remove('border-transparent', 'text-muted-foreground');
                projectionsButton.classList.remove('border-primary', 'text-primary');
                projectionsButton.classList.add('border-transparent', 'text-muted-foreground');
            } else {
                projectionsTab.classList.remove('hidden');
                statsTab.classList.add('hidden');
                projectionsButton.classList.add('border-primary', 'text-primary');
                projectionsButton.classList.remove('border-transparent', 'text-muted-foreground');
                statsButton.classList.remove('border-primary', 'text-primary');
                statsButton.classList.add('border-transparent', 'text-muted-foreground');
            }

            // Update URL without page reload
            const url = new URL(window.location);
            if (tab === 'projections') {
                url.searchParams.set('tab', 'projections');
            } else {
                url.searchParams.delete('tab');
            }
            window.history.pushState({}, '', url);
        }
    </script>
</x-layouts.app>
