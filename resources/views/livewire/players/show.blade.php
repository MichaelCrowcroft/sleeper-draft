<?php

use App\Models\Player;
use Livewire\Volt\Component;

new class extends Component
{
    public Player $player;

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


    public function getProjectionDistributionProperty(): array
    {
        $proj = $this->projections2025;
        $avg = (float) ($proj['average_points_per_game'] ?? 0.0);
        $stdAbove = (float) ($proj['stddev_above'] ?? $avg);
        $stdBelow = (float) ($proj['stddev_below'] ?? $avg);
        $std = max(0.01, ($stdAbove - $avg));

        return [
            'avg' => $avg,
            'std' => $std,
            'lower' => max(0.0, $stdBelow),
            'upper' => max(0.0, $stdAbove),
            'min' => (float) ($proj['min_points'] ?? 0.0),
            'max' => (float) ($proj['max_points'] ?? 0.0),
        ];
    }

    public function getWeeklyActualPointsProperty(): array
    {
        $series = [];
        foreach ($this->weeklyStats as $ws) {
            $stats = $ws->stats ?? [];
            if (isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr'])) {
                $series[] = ['week' => (int) $ws->week, 'value' => (float) $stats['pts_ppr']];
            }
        }
        usort($series, fn($a, $b) => $a['week'] <=> $b['week']);
        return $series;
    }

    public function getWeeklyProjectedPointsProperty(): array
    {
        $series = [];
        foreach ($this->weeklyProjections as $wp) {
            $stats = $wp->stats ?? [];
            $val = null;
            if (isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr'])) {
                $val = (float) $stats['pts_ppr'];
            } elseif (isset($wp->pts_ppr) && is_numeric($wp->pts_ppr)) {
                $val = (float) $wp->pts_ppr;
            }
            if ($val !== null) {
                $series[] = ['week' => (int) $wp->week, 'value' => $val];
            }
        }
        usort($series, fn($a, $b) => $a['week'] <=> $b['week']);
        return $series;
    }

    protected function computeBox(array $values): ?array
    {
        if (empty($values)) {
            return null;
        }
        sort($values);
        $n = count($values);

        $median = function(array $arr): float {
            $m = count($arr);
            $mid = intdiv($m, 2);
            if ($m % 2 === 0) {
                return ( ($arr[$mid - 1] + $arr[$mid]) / 2.0 );
            }
            return (float) $arr[$mid];
        };

        $med = $median($values);
        if ($n <= 2) {
            $q1 = $values[0];
            $q3 = $values[$n - 1];
        } else {
            $mid = intdiv($n, 2);
            $lower = array_slice($values, 0, $mid);
            $upper = array_slice($values, ($n % 2 === 0) ? $mid : $mid + 1);
            $q1 = $median($lower);
            $q3 = $median($upper);
        }

        return [
            'min' => (float) min($values),
            'q1' => (float) $q1,
            'median' => (float) $med,
            'q3' => (float) $q3,
            'max' => (float) max($values),
        ];
    }

    public function getBoxPlotProperty(): array
    {
        $actVals = array_map(fn($p) => (float)$p['value'], $this->weeklyActualPoints);
        $prjVals = array_map(fn($p) => (float)$p['value'], $this->weeklyProjectedPoints);

        $actBox = $this->computeBox($actVals);
        $prjBox = $this->computeBox($prjVals);

        $width = 360.0;
        $height = 180.0;
        $padL = 34.0;
        $padR = 16.0;
        $padT = 8.0;
        $padB = 26.0;
        $plotH = $height - $padT - $padB;
        $plotW = $width - $padL - $padR;

        $boxes = array_values(array_filter([
            ['label' => '2024', 'stats' => $actBox],
            ['label' => '2025', 'stats' => $prjBox],
        ], fn($it) => !is_null($it['stats'])));

        if (empty($boxes)) {
            return [
                'width' => $width,
                'height' => $height,
                'items' => [],
                'ticks' => [],
            ];
        }

        $minVal = min(array_map(fn($b) => $b['stats']['min'], $boxes));
        $maxVal = max(array_map(fn($b) => $b['stats']['max'], $boxes));
        if ($minVal === $maxVal) { $minVal = max(0.0, $minVal - 1.0); $maxVal = $maxVal + 1.0; }

        $scaleY = function (float $v) use ($minVal, $maxVal, $padT, $plotH) {
            $t = ($v - $minVal) / ($maxVal - $minVal);
            return $padT + (1.0 - $t) * $plotH;
        };

        $n = count($boxes);
        $slotW = $plotW / max(1, $n);
        $boxW = min(36.0, $slotW * 0.4);

        $items = [];
        foreach ($boxes as $i => $b) {
            $xCenter = $padL + ($i + 0.5) * $slotW;
            $s = $b['stats'];
            $yMin = round($scaleY($s['min']), 2);
            $yQ1 = round($scaleY($s['q1']), 2);
            $yMedian = round($scaleY($s['median']), 2);
            $yQ3 = round($scaleY($s['q3']), 2);
            $yMax = round($scaleY($s['max']), 2);

            $items[] = [
                'label' => $b['label'],
                'x' => round($xCenter, 2),
                'w' => round($boxW, 2),
                'yMin' => $yMin,
                'yQ1' => $yQ1,
                'yMedian' => $yMedian,
                'yQ3' => $yQ3,
                'yMax' => $yMax,
                // raw values for labels/tooltips
                'vMin' => $s['min'],
                'vQ1' => $s['q1'],
                'vMedian' => $s['median'],
                'vQ3' => $s['q3'],
                'vMax' => $s['max'],
            ];
        }

        // simple 4 ticks (min, 1/3, 2/3, max)
        $ticks = [];
        for ($k = 0; $k <= 3; $k++) {
            $val = $minVal + ($k / 3.0) * ($maxVal - $minVal);
            $ticks[] = ['y' => round($scaleY($val), 2), 'label' => number_format($val, 1)];
        }

        return [
            'width' => $width,
            'height' => $height,
            'items' => $items,
            'ticks' => $ticks,
            'padL' => $padL,
            'padT' => $padT,
            'plotH' => $plotH,
            'slotW' => $slotW,
        ];
    }

    public function getActualMedian2024Property(): ?float
    {
        $vals = array_map(fn($p) => (float)$p['value'], $this->weeklyActualPoints);
        if (empty($vals)) {
            return null;
        }
        sort($vals);
        $n = count($vals);
        $mid = intdiv($n, 2);
        if ($n % 2 === 0) {
            return ( ($vals[$mid - 1] + $vals[$mid]) / 2.0 );
        }
        return (float) $vals[$mid];
    }

    public function getBox2024HorizontalProperty(): array
    {
        $actVals = array_map(fn($p) => (float)$p['value'], $this->weeklyActualPoints);
        $stats = $this->computeBox($actVals);

        $width = 480.0;
        $height = 120.0;
        $padL = 24.0;
        $padR = 24.0;
        $padT = 20.0;
        $padB = 24.0;
        $plotW = $width - $padL - $padR;
        $yMid = $padT + ($height - $padT - $padB) / 2.0;
        $boxH = 24.0;

        if (!$stats) {
            return [
                'width' => $width,
                'height' => $height,
                'exists' => false,
            ];
        }

        $minVal = $stats['min'];
        $maxVal = $stats['max'];
        if ($minVal === $maxVal) {
            $minVal = max(0.0, $minVal - 1.0);
            $maxVal = $maxVal + 1.0;
        }

        $scaleX = function (float $v) use ($minVal, $maxVal, $padL, $plotW) {
            $t = ($v - $minVal) / ($maxVal - $minVal);
            return $padL + $t * $plotW;
        };

        $xMin = round($scaleX($stats['min']), 2);
        $xQ1 = round($scaleX($stats['q1']), 2);
        $xMedian = round($scaleX($stats['median']), 2);
        $xQ3 = round($scaleX($stats['q3']), 2);
        $xMax = round($scaleX($stats['max']), 2);

        return [
            'width' => $width,
            'height' => $height,
            'exists' => true,
            'yMid' => round($yMid, 2),
            'boxH' => $boxH,
            'xMin' => $xMin,
            'xQ1' => $xQ1,
            'xMedian' => $xMedian,
            'xQ3' => $xQ3,
            'xMax' => $xMax,
            'vMin' => $stats['min'],
            'vQ1' => $stats['q1'],
            'vMedian' => $stats['median'],
            'vQ3' => $stats['q3'],
            'vMax' => $stats['max'],
        ];
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

            <!-- Key Stats Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 min-w-0 lg:min-w-[400px]">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border">
                    <div class="text-xl sm:text-2xl font-bold text-green-600">
                        {{ number_format($this->projectionDistribution['avg'] ?? 0, 1) }}
                    </div>
                    <div class="text-xs text-muted-foreground">2025 Proj PPG</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border">
                    <div class="text-xl sm:text-2xl font-bold text-blue-600">
                        @if(!is_null($this->actualMedian2024))
                            {{ number_format($this->actualMedian2024, 1) }}
                        @else
                            —
                        @endif
                    </div>
                    <div class="text-xs text-muted-foreground">2024 Median</div>
                </div>
                @if ($player->adp_formatted || $player->adp)
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border">
                        <div class="text-xl sm:text-2xl font-bold text-purple-600">
                            {{ $player->adp_formatted ?? number_format($player->adp, 1) }}
                        </div>
                        <div class="text-xs text-muted-foreground">ADP</div>
                    </div>
                @endif
                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border">
                    <div class="text-xl sm:text-2xl font-bold text-orange-600">
                        {{ $this->projections2025['games'] ?? 0 }}
                    </div>
                    <div class="text-xs text-muted-foreground">Proj Games</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="space-y-6">
        <div class="sticky top-0 bg-white/80 dark:bg-gray-900/80 backdrop-blur supports-[backdrop-filter]:bg-white/60 dark:supports-[backdrop-filter]:bg-gray-900/60 z-10 pb-3">
            <flux:tabs>
                <flux:tab href="{{ route('players.show', $player->player_id) }}" accent>Overview</flux:tab>
                <flux:tab href="{{ route('players.show.2024', $player->player_id) }}">2024 Performance</flux:tab>
            </flux:tabs>
        </div>

        <!-- Main Overview Content -->
            <div class="space-y-6">
                    <!-- Performance Chart -->
                    @if($this->box2024Horizontal['exists'] ?? false)
                        <flux:callout>
                            <flux:heading size="md" class="mb-4">2024 Performance Distribution</flux:heading>
                            <x-box-whisker-chart :data="$this->box2024Horizontal" />
                        </flux:callout>
                    @endif

                    <!-- Key Insights Grid -->
                    <div class="grid gap-6 lg:grid-cols-2">
                        <!-- 2025 Projections -->
                        <flux:callout>
                            <flux:heading size="md" class="mb-4">2025 Season Projections</flux:heading>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-sm font-medium">Total Points</span>
                                    <span class="font-bold text-green-600">{{ number_format($this->projections2025['total_points'] ?? 0, 1) }}</span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-sm font-medium">Average PPG</span>
                                    <span class="font-bold">{{ number_format($this->projections2025['average_points_per_game'] ?? 0, 1) }}</span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-sm font-medium">Range (±1σ)</span>
                                    <span class="font-bold">{{ number_format($this->projectionDistribution['lower'] ?? 0, 1) }}–{{ number_format($this->projectionDistribution['upper'] ?? 0, 1) }}</span>
                                </div>
                                <div class="flex justify-between items-center py-2">
                                    <span class="text-sm font-medium">Weekly Min/Max</span>
                                    <span class="font-bold">{{ number_format($this->projections2025['min_points'] ?? 0, 1) }}/{{ number_format($this->projections2025['max_points'] ?? 0, 1) }}</span>
                                </div>
                            </div>
                        </flux:callout>

                        <!-- 2024 Season Summary -->
                        <flux:callout>
                            <flux:heading size="md" class="mb-4">2024 Season Results</flux:heading>
                            @if (!empty($this->stats2024))
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                        <span class="text-sm font-medium">Total Points</span>
                                        <span class="font-bold text-blue-600">{{ number_format($this->summary2024['total_points'] ?? 0, 1) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                        <span class="text-sm font-medium">Average PPG</span>
                                        <span class="font-bold">{{ number_format($this->summary2024['average_points_per_game'] ?? 0, 1) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                        <span class="text-sm font-medium">Games Played</span>
                                        <span class="font-bold">{{ $this->summary2024['games_active'] ?? 0 }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2">
                                        <span class="text-sm font-medium">Best Game</span>
                                        <span class="font-bold">{{ number_format($this->summary2024['max_points'] ?? 0, 1) }}</span>
                                    </div>

                                    <!-- Volatility Metrics Section -->
                                    @if(!empty($this->summary2024['volatility']) && !is_null($this->summary2024['volatility']['steadiness_score']))
                                        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                                            <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Volatility Analysis</h5>
                                            <div class="grid grid-cols-2 gap-3 text-xs">
                                                <!-- Steadiness Score -->
                                                <div class="flex justify-between items-center py-1">
                                                    <span class="font-medium">Steadiness Score</span>
                                                    <span class="font-bold text-green-600">{{ number_format($this->summary2024['volatility']['steadiness_score'], 2) }}</span>
                                                </div>

                                                <!-- Safe Floor -->
                                                <div class="flex justify-between items-center py-1">
                                                    <span class="font-medium">Safe Floor</span>
                                                    <span class="font-bold text-blue-600">{{ number_format($this->summary2024['volatility']['safe_floor'], 1) }}</span>
                                                </div>

                                                <!-- Spike Factor -->
                                                @if(!is_null($this->summary2024['volatility']['spike_factor']))
                                                    <div class="flex justify-between items-center py-1">
                                                        <span class="font-medium">Spike Factor</span>
                                                        <span class="font-bold text-orange-600">{{ number_format($this->summary2024['volatility']['spike_factor'], 1) }}</span>
                                                    </div>
                                                @endif

                                                <!-- Consistency Rate -->
                                                @if(!is_null($this->summary2024['volatility']['consistency_rate']))
                                                    <div class="flex justify-between items-center py-1">
                                                        <span class="font-medium">Startable Weeks</span>
                                                        <span class="font-bold text-purple-600">{{ number_format($this->summary2024['volatility']['consistency_rate'], 1) }}%</span>
                                                    </div>
                                                @endif

                                                <!-- Boom Rate -->
                                                @if(!is_null($this->summary2024['volatility']['boom_rate']))
                                                    <div class="flex justify-between items-center py-1">
                                                        <span class="font-medium">Boom Rate</span>
                                                        <span class="font-bold text-red-600">{{ number_format($this->summary2024['volatility']['boom_rate'], 1) }}%</span>
                                                    </div>
                                                @endif

                                                <!-- Bust Rate -->
                                                @if(!is_null($this->summary2024['volatility']['bust_rate']))
                                                    <div class="flex justify-between items-center py-1">
                                                        <span class="font-medium">Bust Rate</span>
                                                        <span class="font-bold text-gray-600">{{ number_format($this->summary2024['volatility']['bust_rate'], 1) }}%</span>
                                                    </div>
                                                @endif

                                                <!-- Usage Volatility -->
                                                @if(!is_null($this->summary2024['volatility']['usage_volatility']))
                                                    <div class="flex justify-between items-center py-1">
                                                        <span class="font-medium">Usage Volatility</span>
                                                        <span class="font-bold text-indigo-600">{{ number_format($this->summary2024['volatility']['usage_volatility'], 2) }}</span>
                                                    </div>
                                                @endif

                                                <!-- Recency Volatility -->
                                                @if(!is_null($this->summary2024['volatility']['recency_volatility']))
                                                    <div class="flex justify-between items-center py-1">
                                                        <span class="font-medium">Recent Volatility</span>
                                                        <span class="font-bold text-teal-600">{{ number_format($this->summary2024['volatility']['recency_volatility'], 1) }}</span>
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- Volatility Insights -->
                                            <div class="mt-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                                <div class="text-xs text-muted-foreground space-y-1">
                                                    @if($this->summary2024['volatility']['steadiness_score'] > 2.0)
                                                        <p class="text-green-700 dark:text-green-300">✓ Highly consistent performer with low volatility</p>
                                                    @elseif($this->summary2024['volatility']['steadiness_score'] > 1.0)
                                                        <p class="text-blue-700 dark:text-blue-300">✓ Moderately consistent with some variance</p>
                                                    @else
                                                        <p class="text-orange-700 dark:text-orange-300">⚠ High volatility - expect significant week-to-week swings</p>
                                                    @endif

                                                    @if(!is_null($this->summary2024['volatility']['consistency_rate']) && $this->summary2024['volatility']['consistency_rate'] > 60)
                                                        <p class="text-green-700 dark:text-green-300">✓ Reliable starter ({{ number_format($this->summary2024['volatility']['consistency_rate'], 0) }}% of weeks)</p>
                                                    @elseif(!is_null($this->summary2024['volatility']['consistency_rate']) && $this->summary2024['volatility']['consistency_rate'] < 40)
                                                        <p class="text-red-700 dark:text-red-300">⚠ Unreliable starter ({{ number_format($this->summary2024['volatility']['consistency_rate'], 0) }}% of weeks)</p>
                                                    @endif

                                                    @if(!is_null($this->summary2024['volatility']['usage_volatility']) && $this->summary2024['volatility']['usage_volatility'] > 0.3)
                                                        <p class="text-orange-700 dark:text-orange-300">⚠ Variable usage patterns - monitor snap counts</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="text-center py-8 text-muted-foreground">
                                    <p>No 2024 season data available</p>
                                </div>
                            @endif
                        </flux:callout>
                    </div>
                </div>
    </div>
</section>
