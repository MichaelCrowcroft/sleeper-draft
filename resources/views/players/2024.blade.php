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

<section class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Back Navigation -->
    <div class="mb-6">
        <flux:button variant="ghost" href="{{ route('players.show', $player->player_id) }}">
            ← Back to Summary
        </flux:button>
    </div>

    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-950/20 dark:to-emerald-950/20 rounded-xl border p-6 sm:p-8 mb-8">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <!-- Player Info -->
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <flux:heading size="xl" class="text-2xl sm:text-3xl font-bold">
                        {{ $player->first_name }} {{ $player->last_name }}
                    </flux:heading>
                    <div class="flex items-center gap-2">
                        <flux:badge variant="primary" size="lg">{{ $player->position }}</flux:badge>
                        <flux:badge variant="outline">{{ $player->team }}</flux:badge>
                        <flux:badge variant="secondary">2024 Season</flux:badge>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                    @if ($player->age)
                        <span class="flex items-center gap-1">
                            <span class="font-medium">Age:</span> {{ $player->age }}
                        </span>
                    @endif
                    @if ($player->height && $player->weight)
                        <span class="flex items-center gap-1">
                            <span class="font-medium">Size:</span> {{ $player->height }}, {{ $player->weight }} lbs
                        </span>
                    @endif
                    @if ($player->college)
                        <span class="flex items-center gap-1">
                            <span class="font-medium">College:</span> {{ $player->college }}
                        </span>
                    @endif
                </div>

                @if ($player->injury_status && $player->injury_status !== 'Healthy')
                    <div class="pt-2">
                        <flux:badge variant="destructive">
                            {{ $player->injury_status }}@if ($player->injury_body_part) - {{ $player->injury_body_part }}@endif
                        </flux:badge>
                    </div>
                @endif
            </div>

            <!-- Key Stats Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 min-w-0 lg:min-w-[400px]">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border">
                    <div class="text-xl sm:text-2xl font-bold text-green-600">
                        {{ number_format($this->summary2024['total_points'] ?? 0, 1) }}
                    </div>
                    <div class="text-xs text-muted-foreground">Total Points</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border">
                    <div class="text-xl sm:text-2xl font-bold text-blue-600">
                        {{ number_format($this->summary2024['average_points_per_game'] ?? 0, 1) }}
                    </div>
                    <div class="text-xs text-muted-foreground">Average PPG</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border">
                    <div class="text-xl sm:text-2xl font-bold text-purple-600">
                        {{ $this->summary2024['games_active'] ?? 0 }}
                    </div>
                    <div class="text-xs text-muted-foreground">Games Played</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border">
                    <div class="text-xl sm:text-2xl font-bold text-orange-600">
                        {{ number_format($this->summary2024['max_points'] ?? 0, 1) }}
                    </div>
                    <div class="text-xs text-muted-foreground">Best Game</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="space-y-6">
        <!-- Weekly Performance -->
        <flux:callout>
            <flux:heading size="md" class="mb-4">2024 Weekly Performance</flux:heading>
            @if ($this->weeklyStats->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-3 py-3 text-left font-semibold">Week</th>
                                <th class="px-3 py-3 text-left font-semibold">Pos Rank</th>
                                <th class="px-3 py-3 text-left font-semibold">Points</th>
                                <th class="px-3 py-3 text-left font-semibold">Target %</th>
                                <th class="px-3 py-3 text-left font-semibold">Snap %</th>
                                @if ($this->position === 'QB')
                                    <th class="px-3 py-3 text-left font-semibold">Pass Yds</th>
                                    <th class="px-3 py-3 text-left font-semibold">Pass TDs</th>
                                @elseif ($this->position === 'RB')
                                    <th class="px-3 py-3 text-left font-semibold">Rush Yds</th>
                                    <th class="px-3 py-3 text-left font-semibold">Rush TDs</th>
                                    <th class="px-3 py-3 text-left font-semibold">Rec</th>
                                @elseif (in_array($this->position, ['WR', 'TE']))
                                    <th class="px-3 py-3 text-left font-semibold">Rec</th>
                                    <th class="px-3 py-3 text-left font-semibold">Rec Yds</th>
                                    <th class="px-3 py-3 text-left font-semibold">TDs</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @php
                                // Precompute weekly ranks once for performance
                                $weeklyRanks = $player->getWeeklyPositionRanksForSeason(2024);
                            @endphp
                            @foreach ($this->weeklyStats->sortBy('week') as $weekStat)
                                @php
                                    $stats = $weekStat->stats ?? [];
                                    // Determine snap percentage from available fields
                                    if (isset($stats['snap_pct']) && is_numeric($stats['snap_pct'])) {
                                        $snap = number_format($stats['snap_pct'], 1) . '%';
                                    } elseif (isset($stats['off_snp']) && isset($stats['tm_off_snp']) && is_numeric($stats['off_snp']) && is_numeric($stats['tm_off_snp']) && (float)$stats['tm_off_snp'] > 0) {
                                        $snap = number_format(((float)$stats['off_snp'] / (float)$stats['tm_off_snp']) * 100, 1) . '%';
                                    } elseif (isset($stats['snap_share']) && is_numeric($stats['snap_share'])) {
                                        // Some providers give 0-1 share
                                        $snap = number_format(((float)$stats['snap_share']) * 100, 1) . '%';
                                    } else {
                                        $snap = '—';
                                    }
                                    $rank = $weeklyRanks[$weekStat->week] ?? null;
                                    // Target share for the week if team totals available
                                    $tgtShare = null;
                                    if (isset($stats['rec_tgt']) && is_numeric($stats['rec_tgt']) && $player->team) {
                                        $teamTotalTgt = \App\Models\Player::getTeamTargetsForWeek(2024, (int) $weekStat->week, (string) $player->team);
                                        if ($teamTotalTgt > 0) {
                                            $tgtShare = number_format(((float) $stats['rec_tgt'] / $teamTotalTgt) * 100.0, 1) . '%';
                                        }
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-3 py-2 font-medium">{{ $weekStat->week }}</td>
                                    <td class="px-3 py-2">@if($rank) {{ $player->position }}{{ $rank }} @else — @endif</td>
                                    <td class="px-3 py-2 font-semibold">{{ isset($stats['pts_ppr']) ? number_format($stats['pts_ppr'], 1) : '—' }}</td>
                                    <td class="px-3 py-2">{{ $tgtShare ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $snap }}</td>
                                    @if ($this->position === 'QB')
                                        <td class="px-3 py-2">{{ $stats['pass_yd'] ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ $stats['pass_td'] ?? '—' }}</td>
                                    @elseif ($this->position === 'RB')
                                        <td class="px-3 py-2">{{ $stats['rush_yd'] ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ $stats['rush_td'] ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ $stats['rec'] ?? '—' }}</td>
                                    @elseif (in_array($this->position, ['WR', 'TE']))
                                        <td class="px-3 py-2">{{ $stats['rec'] ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ $stats['rec_yd'] ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ $stats['rec_td'] ?? '—' }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8 text-muted-foreground">
                    <p>No weekly performance data available</p>
                </div>
            @endif
        </flux:callout>

        <!-- 2025 Weekly Projections -->
        <flux:callout>
            <flux:heading size="md" class="mb-4">2025 Weekly Projections</flux:heading>
            @if ($this->weeklyProjections->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-3 py-3 text-left font-semibold">Week</th>
                                <th class="px-3 py-3 text-left font-semibold">Projected PPR</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($this->weeklyProjections->sortBy('week') as $proj)
                                @php $stats = $proj->stats ?? []; @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-3 py-2 font-medium">{{ $proj->week }}</td>
                                    <td class="px-3 py-2 font-semibold">
                                        @if(isset($stats['pts_ppr']))
                                            {{ number_format($stats['pts_ppr'], 1) }}
                                        @elseif(isset($proj->pts_ppr))
                                            {{ number_format($proj->pts_ppr, 1) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8 text-muted-foreground">
                    <p>No weekly projections available</p>
                </div>
            @endif
        </flux:callout>

        @if (!empty($this->stats2024))
            <flux:callout>
                <flux:heading size="md" class="mb-6">2024 Detailed Stats ({{ $this->position }})</flux:heading>

                @if ($this->position === 'QB')
                    <!-- QB Stats -->
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-4">
                            <h4 class="font-semibold text-base text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">Passing</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Passing Yards</span>
                                    <span class="font-bold">{{ number_format($this->qbStats['pass_yd']) }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Passing TDs</span>
                                    <span class="font-bold">{{ $this->qbStats['pass_td'] }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Interceptions</span>
                                    <span class="font-bold">{{ $this->qbStats['pass_int'] }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Completions</span>
                                    <span class="font-bold">{{ $this->qbStats['pass_cmp'] }}/{{ $this->qbStats['pass_att'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <h4 class="font-semibold text-base text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">Rushing</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Rushing Yards</span>
                                    <span class="font-bold">{{ number_format($this->qbStats['rush_yd']) }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Rushing TDs</span>
                                    <span class="font-bold">{{ $this->qbStats['rush_td'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                @elseif ($this->position === 'RB')
                    <!-- RB Stats -->
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-4">
                            <h4 class="font-semibold text-base text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">Rushing</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Rushing Yards</span>
                                    <span class="font-bold">{{ number_format($this->rbStats['rush_yd']) }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Rushing TDs</span>
                                    <span class="font-bold">{{ $this->rbStats['rush_td'] }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Carries</span>
                                    <span class="font-bold">{{ $this->rbStats['rush_att'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <h4 class="font-semibold text-base text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">Receiving</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Receptions</span>
                                    <span class="font-bold">{{ $this->rbStats['rec'] }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Receiving Yards</span>
                                    <span class="font-bold">{{ number_format($this->rbStats['rec_yd']) }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Receiving TDs</span>
                                    <span class="font-bold">{{ $this->rbStats['rec_td'] }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Targets</span>
                                    <span class="font-bold">{{ $this->rbStats['rec_tgt'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                @elseif (in_array($this->position, ['WR', 'TE']))
                    <!-- WR/TE Stats -->
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-4">
                            <h4 class="font-semibold text-base text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">Receiving</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Receptions</span>
                                    <span class="font-bold">{{ $this->wrTeStats['rec'] }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Receiving Yards</span>
                                    <span class="font-bold">{{ number_format($this->wrTeStats['rec_yd']) }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Receiving TDs</span>
                                    <span class="font-bold">{{ $this->wrTeStats['rec_td'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <h4 class="font-semibold text-base text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">Efficiency</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Targets</span>
                                    <span class="font-bold">{{ $this->wrTeStats['rec_tgt'] }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Longest Reception</span>
                                    <span class="font-bold">{{ $this->wrTeStats['rec_lng'] }} yds</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Yards Per Reception</span>
                                    <span class="font-bold">{{ number_format($this->wrTeStats['rec_ypr'], 1) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                @elseif ($this->position === 'K')
                    <!-- K Stats -->
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-4">
                            <h4 class="font-semibold text-base text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">Field Goals</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Field Goals Made</span>
                                    <span class="font-bold">{{ $this->kStats['fgm'] }}/{{ $this->kStats['fga'] }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">FG Percentage</span>
                                    <span class="font-bold">{{ $this->kStats['fg_pct'] }}%</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <h4 class="font-semibold text-base text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">Extra Points</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Extra Points Made</span>
                                    <span class="font-bold">{{ $this->kStats['xpm'] }}/{{ $this->kStats['xpa'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                @elseif ($this->position === 'DEF')
                    <!-- DEF Stats -->
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-4">
                            <h4 class="font-semibold text-base text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">Defensive Stats</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Interceptions</span>
                                    <span class="font-bold">{{ $this->defStats['def_int'] }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Sacks</span>
                                    <span class="font-bold">{{ number_format($this->defStats['def_sack'], 1) }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Tackles</span>
                                    <span class="font-bold">{{ $this->defStats['def_tkl'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <h4 class="font-semibold text-base text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">Turnovers & TDs</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Forced Fumbles</span>
                                    <span class="font-bold">{{ $this->defStats['def_ff'] }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Defensive TDs</span>
                                    <span class="font-bold">{{ $this->defStats['def_td'] }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-sm">Points Against</span>
                                    <span class="font-bold">{{ $this->defStats['def_pa'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                @else
                    <!-- Default/Unknown Position -->
                    <div class="text-center py-12">
                        <div class="text-muted-foreground mb-4">
                            <p class="text-lg">Position-specific stats not available for {{ $this->position }} position.</p>
                        </div>
                        @if (!empty($this->stats2024))
                            <div class="text-xs text-muted-foreground">
                                <p><strong>Available stats:</strong> {{ implode(', ', array_keys($this->stats2024)) }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </flux:callout>
        @else
            <flux:callout>
                <div class="text-center py-12">
                    <div class="text-muted-foreground">
                        <p class="text-lg">No detailed stats available for this player.</p>
                    </div>
                </div>
            </flux:callout>
        @endif
    </div>
</section>
