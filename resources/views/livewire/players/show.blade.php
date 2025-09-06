<?php

use App\Models\Player;
use Livewire\Volt\Component;

new class extends Component {
    public Player $player;

    public function mount($playerId)
    {
        $this->player = Player::where('player_id', $playerId)->firstOrFail();
    }

    public function getStats2024Property()
    {
        return $this->player->getSeason2024Totals();
    }

    public function getSummary2024Property()
    {
        return $this->player->getSeason2024Summary();
    }

    public function getProjections2025Property()
    {
        return $this->player->getSeason2025ProjectionSummary();
    }

    public function getWeeklyStatsProperty()
    {
        return $this->player->getStatsForSeason(2024)->get();
    }

    public function getWeeklyProjectionsProperty()
    {
        return $this->player->getProjectionsForSeason(2025)->get();
    }

    public function getPositionProperty(): string
    {
        // Use fantasy_positions if available, fallback to position
        $fantasyPositions = $this->player->fantasy_positions;
        if (is_array($fantasyPositions) && !empty($fantasyPositions)) {
            return $fantasyPositions[0];
        }
        return $this->player->position ?? 'UNKNOWN';
    }

    public function getQbStatsProperty(): array
    {
        $stats = $this->stats2024;
        return [
            'pass_yd' => $stats['pass_yd'] ?? 0,
            'pass_td' => $stats['pass_td'] ?? 0,
            'pass_int' => $stats['pass_int'] ?? 0,
            'pass_cmp' => $stats['pass_cmp'] ?? 0,
            'pass_att' => $stats['pass_att'] ?? 0,
            'rush_yd' => $stats['rush_yd'] ?? 0,
            'rush_td' => $stats['rush_td'] ?? 0,
        ];
    }

    public function getRbStatsProperty(): array
    {
        $stats = $this->stats2024;
        return [
            'rush_yd' => $stats['rush_yd'] ?? 0,
            'rush_td' => $stats['rush_td'] ?? 0,
            'rush_att' => $stats['rush_att'] ?? 0,
            'rec' => $stats['rec'] ?? 0,
            'rec_yd' => $stats['rec_yd'] ?? 0,
            'rec_td' => $stats['rec_td'] ?? 0,
            'rec_tgt' => $stats['rec_tgt'] ?? 0,
        ];
    }

    public function getWrTeStatsProperty(): array
    {
        $stats = $this->stats2024;
        return [
            'rec' => $stats['rec'] ?? 0,
            'rec_yd' => $stats['rec_yd'] ?? 0,
            'rec_td' => $stats['rec_td'] ?? 0,
            'rec_tgt' => $stats['rec_tgt'] ?? 0,
            'rec_lng' => $stats['rec_lng'] ?? 0,
            'rec_ypr' => $stats['rec_ypr'] ?? 0,
        ];
    }

    public function getKStatsProperty(): array
    {
        $stats = $this->stats2024;
        return [
            'fgm' => $stats['fgm'] ?? 0,
            'fga' => $stats['fga'] ?? 0,
            'xpm' => $stats['xpm'] ?? 0,
            'xpa' => $stats['xpa'] ?? 0,
            'fg_pct' => isset($stats['fga']) && $stats['fga'] > 0
                ? round(($stats['fgm'] ?? 0) / $stats['fga'] * 100, 1)
                : 0,
        ];
    }

    public function getDefStatsProperty(): array
    {
        $stats = $this->stats2024;
        return [
            'def_int' => $stats['def_int'] ?? 0,
            'def_sack' => $stats['def_sack'] ?? 0,
            'def_tkl' => $stats['def_tkl'] ?? 0,
            'def_ff' => $stats['def_ff'] ?? 0,
            'def_td' => $stats['def_td'] ?? 0,
            'def_pa' => $stats['def_pa'] ?? 0,
        ];
    }
}; ?>

<section class="w-full">
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
            @if (!empty($this->stats2024))
                <flux:callout>
                    <flux:heading size="md" class="mb-4">2024 Season Stats</flux:heading>
                    <div class="space-y-3">
                        @if (isset($this->summary2024['total_points']))
                            <div class="flex justify-between">
                                <span>Total Points:</span>
                                <span class="font-semibold">{{ number_format($this->summary2024['total_points'], 1) }}</span>
                            </div>
                        @endif
                        @if (isset($this->summary2024['games_active']))
                            <div class="flex justify-between">
                                <span>Games Played:</span>
                                <span class="font-semibold">{{ $this->summary2024['games_active'] }}</span>
                            </div>
                        @endif
                        @if (isset($this->summary2024['average_points_per_game']) && $this->summary2024['games_active'] > 0)
                            <div class="flex justify-between">
                                <span>Avg PPG:</span>
                                <span class="font-semibold">{{ number_format($this->summary2024['average_points_per_game'], 1) }}</span>
                            </div>
                        @endif
                        @if (isset($this->summary2024['max_points']))
                            <div class="flex justify-between">
                                <span>Best Game:</span>
                                <span class="font-semibold">{{ number_format($this->summary2024['max_points'], 1) }}</span>
                            </div>
                        @endif
                    </div>
                </flux:callout>
            @endif

            <!-- 2025 Projections -->
            @if (!empty($this->projections2025))
                <flux:callout>
                    <flux:heading size="md" class="mb-4">2025 Projections</flux:heading>
                    <div class="space-y-3">
                        @if (isset($this->projections2025['total_points']))
                            <div class="flex justify-between">
                                <span>Projected Points:</span>
                                <span class="font-semibold">{{ number_format($this->projections2025['total_points'], 1) }}</span>
                            </div>
                        @endif
                        @if (isset($this->projections2025['games']))
                            <div class="flex justify-between">
                                <span>Projected Games:</span>
                                <span class="font-semibold">{{ $this->projections2025['games'] }}</span>
                            </div>
                        @endif
                        @if (isset($this->projections2025['average_points_per_game']) && $this->projections2025['games'] > 0)
                            <div class="flex justify-between">
                                <span>Projected PPG:</span>
                                <span class="font-semibold">{{ number_format($this->projections2025['average_points_per_game'], 1) }}</span>
                            </div>
                        @endif
                    </div>
                </flux:callout>
            @endif
        </div>

        <!-- Position-Specific Stats -->
        @if (!empty($this->stats2024))
        <flux:callout>
                <flux:heading size="md" class="mb-4">2024 Detailed Stats ({{ $this->position }})</flux:heading>

                <!-- QB Stats -->
                @if ($this->position === 'QB')
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-3">
                            <h4 class="font-medium text-sm text-muted-foreground">Passing</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Passing Yards:</span>
                                    <span class="font-semibold">{{ number_format($this->qbStats['pass_yd']) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Passing TDs:</span>
                                    <span class="font-semibold">{{ $this->qbStats['pass_td'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Interceptions:</span>
                                    <span class="font-semibold">{{ $this->qbStats['pass_int'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Completions:</span>
                                    <span class="font-semibold">{{ $this->qbStats['pass_cmp'] }}/{{ $this->qbStats['pass_att'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <h4 class="font-medium text-sm text-muted-foreground">Rushing</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Rushing Yards:</span>
                                    <span class="font-semibold">{{ number_format($this->qbStats['rush_yd']) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Rushing TDs:</span>
                                    <span class="font-semibold">{{ $this->qbStats['rush_td'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- RB Stats -->
                @elseif ($this->position === 'RB')
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-3">
                            <h4 class="font-medium text-sm text-muted-foreground">Rushing</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Rushing Yards:</span>
                                    <span class="font-semibold">{{ number_format($this->rbStats['rush_yd']) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Rushing TDs:</span>
                                    <span class="font-semibold">{{ $this->rbStats['rush_td'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Carries:</span>
                                    <span class="font-semibold">{{ $this->rbStats['rush_att'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <h4 class="font-medium text-sm text-muted-foreground">Receiving</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Receptions:</span>
                                    <span class="font-semibold">{{ $this->rbStats['rec'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Receiving Yards:</span>
                                    <span class="font-semibold">{{ number_format($this->rbStats['rec_yd']) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Receiving TDs:</span>
                                    <span class="font-semibold">{{ $this->rbStats['rec_td'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Targets:</span>
                                    <span class="font-semibold">{{ $this->rbStats['rec_tgt'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- WR/TE Stats -->
                @elseif (in_array($this->position, ['WR', 'TE']))
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-3">
                            <h4 class="font-medium text-sm text-muted-foreground">Receiving Stats</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Receptions:</span>
                                    <span class="font-semibold">{{ $this->wrTeStats['rec'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Receiving Yards:</span>
                                    <span class="font-semibold">{{ number_format($this->wrTeStats['rec_yd']) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Receiving TDs:</span>
                                    <span class="font-semibold">{{ $this->wrTeStats['rec_td'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <h4 class="font-medium text-sm text-muted-foreground">Efficiency</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Targets:</span>
                                    <span class="font-semibold">{{ $this->wrTeStats['rec_tgt'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Longest Reception:</span>
                                    <span class="font-semibold">{{ $this->wrTeStats['rec_lng'] }} yds</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Yards Per Reception:</span>
                                    <span class="font-semibold">{{ number_format($this->wrTeStats['rec_ypr'], 1) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- K Stats -->
                @elseif ($this->position === 'K')
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-3">
                            <h4 class="font-medium text-sm text-muted-foreground">Field Goals</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Field Goals Made:</span>
                                    <span class="font-semibold">{{ $this->kStats['fgm'] }}/{{ $this->kStats['fga'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>FG Percentage:</span>
                                    <span class="font-semibold">{{ $this->kStats['fg_pct'] }}%</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <h4 class="font-medium text-sm text-muted-foreground">Extra Points</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Extra Points Made:</span>
                                    <span class="font-semibold">{{ $this->kStats['xpm'] }}/{{ $this->kStats['xpa'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- DEF Stats -->
                @elseif ($this->position === 'DEF')
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-3">
                            <h4 class="font-medium text-sm text-muted-foreground">Defensive Stats</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Interceptions:</span>
                                    <span class="font-semibold">{{ $this->defStats['def_int'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Sacks:</span>
                                    <span class="font-semibold">{{ number_format($this->defStats['def_sack'], 1) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Tackles:</span>
                                    <span class="font-semibold">{{ $this->defStats['def_tkl'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <h4 class="font-medium text-sm text-muted-foreground">Turnovers & TDs</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Forced Fumbles:</span>
                                    <span class="font-semibold">{{ $this->defStats['def_ff'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Defensive TDs:</span>
                                    <span class="font-semibold">{{ $this->defStats['def_td'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Points Against:</span>
                                    <span class="font-semibold">{{ $this->defStats['def_pa'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- Default/Unknown Position -->
                @else
            <div class="space-y-4">
                        <div class="text-center py-8 text-muted-foreground">
                            Position-specific stats not available for {{ $this->position }} position.
                        </div>
                        @if (!empty($this->stats2024))
                            <div class="text-xs">
                                <strong>Available stats:</strong> {{ implode(', ', array_keys($this->stats2024)) }}
                            </div>
                        @endif
                    </div>
                @endif
            </flux:callout>

            <!-- Weekly Breakdown Table (Simplified) -->
                @if ($this->weeklyStats->count() > 0)
                <flux:callout>
                    <flux:heading size="md" class="mb-4">Weekly Performance</flux:heading>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Week</th>
                                        <th class="px-3 py-2 text-left">Points</th>
                                    @if ($this->position === 'QB')
                                        <th class="px-3 py-2 text-left">Pass Yds</th>
                                        <th class="px-3 py-2 text-left">Pass TDs</th>
                                    @elseif ($this->position === 'RB')
                                        <th class="px-3 py-2 text-left">Rush Yds</th>
                                        <th class="px-3 py-2 text-left">Rush TDs</th>
                                        <th class="px-3 py-2 text-left">Rec</th>
                                    @elseif (in_array($this->position, ['WR', 'TE']))
                                        <th class="px-3 py-2 text-left">Rec</th>
                                        <th class="px-3 py-2 text-left">Rec Yds</th>
                                        <th class="px-3 py-2 text-left">TDs</th>
                                    @else
                                        <th class="px-3 py-2 text-left">Pass Yds</th>
                                        <th class="px-3 py-2 text-left">Rush Yds</th>
                                        <th class="px-3 py-2 text-left">Rec Yds</th>
                                    @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach ($this->weeklyStats->sortBy('week') as $weekStat)
                                        @php
                                            $stats = $weekStat->stats ?? [];
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-2 font-medium">{{ $weekStat->week }}</td>
                                            <td class="px-3 py-2">{{ isset($stats['pts_ppr']) ? number_format($stats['pts_ppr'], 1) : '-' }}</td>
                                        @if ($this->position === 'QB')
                                            <td class="px-3 py-2">{{ $stats['pass_yd'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $stats['pass_td'] ?? '-' }}</td>
                                        @elseif ($this->position === 'RB')
                                            <td class="px-3 py-2">{{ $stats['rush_yd'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $stats['rush_td'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $stats['rec'] ?? '-' }}</td>
                                        @elseif (in_array($this->position, ['WR', 'TE']))
                                            <td class="px-3 py-2">{{ $stats['rec'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $stats['rec_yd'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $stats['rec_td'] ?? '-' }}</td>
                                        @else
                                            <td class="px-3 py-2">{{ $stats['pass_yd'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $stats['rush_yd'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $stats['rec_yd'] ?? '-' }}</td>
                                        @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                </flux:callout>
            @endif
                @else
            <flux:callout>
                <flux:heading size="md" class="mb-4">2024 Stats</flux:heading>
                    <div class="text-center py-8 text-muted-foreground">
                        No 2024 stats available for this player.
                    </div>
            </flux:callout>
                @endif
    </div>
</section>