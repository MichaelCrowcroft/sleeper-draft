<?php

use App\Models\Player;
use Livewire\Volt\Component;

new class extends Component {
    public Player $player;

    public function mount($playerId)
    {
        $this->player = Player::where('player_id', $playerId)->firstOrFail();
    }

    public function getWeeklyStatsProperty()
    {
        return $this->player->getStatsForSeason(2024)->get();
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
}; ?>

<section class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Back Navigation -->
    <div class="mb-6">
        <flux:button variant="ghost" href="{{ route('players.index') }}">
            ← Back to Players
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
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="space-y-6">
        <flux:tabs>
            <flux:tab href="{{ route('players.show', $player->player_id) }}">Overview</flux:tab>
            <flux:tab href="{{ route('players.show.2024', $player->player_id) }}" current>2024 Performance</flux:tab>
        </flux:tabs>

        <!-- 2024 Performance Content -->
        <div class="space-y-6">
            <!-- 2024 Weekly Performance -->
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
        </div>
    </div>
</section>
