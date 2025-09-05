<x-layouts.app :title="__('Players')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">Players</flux:heading>
                <p class="text-muted-foreground mt-1">Browse and filter fantasy football players</p>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <flux:callout>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary">{{ number_format($stats['total_players']) }}</div>
                    <div class="text-sm text-muted-foreground">Total Players</div>
                </div>
            </flux:callout>

            <flux:callout>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600">{{ $stats['injured_players'] }}</div>
                    <div class="text-sm text-muted-foreground">Injured Players</div>
                </div>
            </flux:callout>

            <flux:callout>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ count($stats['players_by_position']) }}</div>
                    <div class="text-sm text-muted-foreground">Positions</div>
                </div>
            </flux:callout>

            <flux:callout>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ count($stats['players_by_team']) }}</div>
                    <div class="text-sm text-muted-foreground">Teams</div>
                </div>
            </flux:callout>
        </div>

        <!-- Filters -->
        <flux:callout>
            <form method="GET" action="{{ route('players.index') }}" class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                <!-- Search -->
                <div>
                    <flux:input
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search players..."
                        type="search"
                    />
                </div>

                <!-- Position Filter -->
                <div>
                    <flux:select name="position">
                        <option value="">All Positions</option>
                        @foreach ($availablePositions as $pos)
                            <option value="{{ $pos }}" {{ $position === $pos ? 'selected' : '' }}>{{ $pos }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Team Filter -->
                <div>
                    <flux:select name="team">
                        <option value="">All Teams</option>
                        @foreach ($availableTeams as $teamCode)
                            <option value="{{ $teamCode }}" {{ $team === $teamCode ? 'selected' : '' }}>{{ $teamCode }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- League Selection -->
                <div>
                    <flux:select name="league_id">
                        <option value="">No League Selected</option>
                        @foreach ($leagues as $league)
                            <option value="{{ $league['id'] }}" {{ $selectedLeagueId === $league['id'] ? 'selected' : '' }}>{{ $league['name'] }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Submit Button -->
                <div>
                    <flux:button type="submit" variant="primary">Filter</flux:button>
                </div>
            </form>
        </flux:callout>

        <!-- Active Filters Summary -->
        @if ($search || $position || $team || $selectedLeagueId)
            <div class="flex flex-wrap gap-2">
                @if ($search)
                    <flux:badge variant="outline">
                        Search: {{ $search }}
                        <a href="{{ route('players.index', array_filter(request()->query(), fn($v, $k) => $k !== 'search', ARRAY_FILTER_USE_BOTH)) }}" class="ml-1 hover:text-destructive">×</a>
                    </flux:badge>
                @endif
                @if ($position)
                    <flux:badge variant="outline">
                        Position: {{ $position }}
                        <a href="{{ route('players.index', array_filter(request()->query(), fn($v, $k) => $k !== 'position', ARRAY_FILTER_USE_BOTH)) }}" class="ml-1 hover:text-destructive">×</a>
                    </flux:badge>
                @endif
                @if ($team)
                    <flux:badge variant="outline">
                        Team: {{ $team }}
                        <a href="{{ route('players.index', array_filter(request()->query(), fn($v, $k) => $k !== 'team', ARRAY_FILTER_USE_BOTH)) }}" class="ml-1 hover:text-destructive">×</a>
                    </flux:badge>
                @endif
                @if ($selectedLeagueId)
                    @php
                        $selectedLeague = collect($leagues)->firstWhere('id', $selectedLeagueId);
                    @endphp
                    <flux:badge variant="outline">
                        League: {{ $selectedLeague['name'] ?? 'Unknown' }}
                        <a href="{{ route('players.index', array_filter(request()->query(), fn($v, $k) => $k !== 'league_id', ARRAY_FILTER_USE_BOTH)) }}" class="ml-1 hover:text-destructive">×</a>
                    </flux:badge>
                @endif
            </div>
        @endif

        <!-- Players Table -->
        <div class="rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-medium">Player</th>
                            <th class="px-4 py-3 text-left text-sm font-medium">Position</th>
                            <th class="px-4 py-3 text-left text-sm font-medium">Team</th>
                            <th class="px-4 py-3 text-left text-sm font-medium">ADP</th>
                            <th class="px-4 py-3 text-left text-sm font-medium">2024 PPG</th>
                            <th class="px-4 py-3 text-left text-sm font-medium">2025 Proj</th>
                            <th class="px-4 py-3 text-left text-sm font-medium">Next Matchup</th>
                            <th class="px-4 py-3 text-left text-sm font-medium">Injury Status</th>
                            @if ($selectedLeagueId)
                                <th class="px-4 py-3 text-left text-sm font-medium">League Status</th>
                            @endif
                            <th class="px-4 py-3 text-left text-sm font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($players as $player)
                            <tr class="hover:bg-muted/50">
                                <td class="px-4 py-3">
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ $player->first_name }} {{ $player->last_name }}</span>
                                        @if ($player->age)
                                            <span class="text-xs text-muted-foreground">{{ $player->age }} years old</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge variant="secondary">{{ $player->position }}</flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge variant="outline">{{ $player->team }}</flux:badge>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($player->adp_formatted)
                                        {{ $player->adp_formatted }}
                                    @elseif ($player->adp)
                                        {{ number_format($player->adp, 1) }}
                                    @else
                                        <span class="text-muted-foreground">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if (isset($player->season_2024_summary) && isset($player->season_2024_summary['average_points_per_game']) && $player->season_2024_summary['average_points_per_game'] > 0)
                                        {{ number_format($player->season_2024_summary['average_points_per_game'], 1) }}
                                    @else
                                        <span class="text-muted-foreground">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if (isset($player->season_2025_projections) && isset($player->season_2025_projections['average_points_per_game']) && $player->season_2025_projections['average_points_per_game'] > 0)
                                        {{ number_format($player->season_2025_projections['average_points_per_game'], 1) }}
                                    @else
                                        <span class="text-muted-foreground">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if (isset($player->next_matchup_projection) && isset($player->next_matchup_projection['projected_points']) && $player->next_matchup_projection['projected_points'] > 0)
                                        {{ $player->next_matchup_projection['projected_points'] }}
                                        <div class="text-xs text-muted-foreground">Week {{ $player->next_matchup_projection['week'] }}</div>
                                    @else
                                        <span class="text-muted-foreground">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($player->injury_status && $player->injury_status !== 'Healthy')
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-red-600">{{ $player->injury_status }}</span>
                                            @if ($player->injury_body_part)
                                                <span class="text-xs text-muted-foreground">{{ $player->injury_body_part }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-sm text-green-600">Healthy</span>
                                    @endif
                                </td>
                                @if ($selectedLeagueId)
                                    <td class="px-4 py-3">
                                        @if (isset($player->league_status) && $player->league_status['status'] === 'owned')
                                            <flux:badge variant="default">{{ $player->league_status['team_name'] }}</flux:badge>
                                        @else
                                            <span class="text-sm text-muted-foreground">Free Agent</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-4 py-3">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        href="{{ route('players.show', $player->player_id) }}"
                                    >
                                        View Details
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $selectedLeagueId ? 10 : 9 }}" class="px-4 py-8 text-center text-muted-foreground">
                                    No players found matching your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($players->hasPages())
                <div class="border-t px-4 py-3">
                    {{ $players->appends(request()->query())->links() }}
                </div>
            @endif
        </div>

        <!-- Results Summary -->
        <div class="text-sm text-muted-foreground">
            Showing {{ $players->count() }} of {{ $players->total() }} players
        </div>
    </div>
</x-layouts.app>
