<?php

use App\Models\Player;
use Livewire\Volt\Component;

new class extends Component {
    public Player $player;

    public string $summaryTab = '2025';

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

        $width = 360.0;
        $height = 100.0;
        $padL = 12.0;
        $padR = 12.0;
        $padT = 16.0;
        $padB = 20.0;
        $plotW = $width - $padL - $padR;
        $yMid = $padT + ($height - $padT - $padB) / 2.0;
        $boxH = 18.0;

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

        <!-- Snapshot: Distribution + Key Numbers -->
        <flux:callout>
            <div class="flex flex-col gap-4">
                <flux:heading size="md">Performance Snapshot</flux:heading>

                <!-- 2024 Horizontal Box & Whisker (no axes, labeled) -->
                <div class="w-full">
                    <svg viewBox="0 0 {{ $this->box2024Horizontal['width'] }} {{ $this->box2024Horizontal['height'] }}" class="w-full h-[120px]">
                        @if($this->box2024Horizontal['exists'])
                            <!-- whiskers -->
                            <line x1="{{ $this->box2024Horizontal['xMin'] }}" x2="{{ $this->box2024Horizontal['xQ1'] }}" y1="{{ $this->box2024Horizontal['yMid'] }}" y2="{{ $this->box2024Horizontal['yMid'] }}" stroke="#16a34a" stroke-width="2">
                                <title>Min: {{ number_format($this->box2024Horizontal['vMin'], 1) }}</title>
                            </line>
                            <line x1="{{ $this->box2024Horizontal['xQ3'] }}" x2="{{ $this->box2024Horizontal['xMax'] }}" y1="{{ $this->box2024Horizontal['yMid'] }}" y2="{{ $this->box2024Horizontal['yMid'] }}" stroke="#16a34a" stroke-width="2">
                                <title>Max: {{ number_format($this->box2024Horizontal['vMax'], 1) }}</title>
                            </line>
                            <!-- min/max caps -->
                            <line x1="{{ $this->box2024Horizontal['xMin'] }}" x2="{{ $this->box2024Horizontal['xMin'] }}" y1="{{ $this->box2024Horizontal['yMid'] - 6 }}" y2="{{ $this->box2024Horizontal['yMid'] + 6 }}" stroke="#16a34a" stroke-width="2" />
                            <line x1="{{ $this->box2024Horizontal['xMax'] }}" x2="{{ $this->box2024Horizontal['xMax'] }}" y1="{{ $this->box2024Horizontal['yMid'] - 6 }}" y2="{{ $this->box2024Horizontal['yMid'] + 6 }}" stroke="#16a34a" stroke-width="2" />

                            <!-- box (Q1–Q3) -->
                            <rect x="{{ $this->box2024Horizontal['xQ1'] }}" y="{{ $this->box2024Horizontal['yMid'] - ($this->box2024Horizontal['boxH']/2) }}" width="{{ max(1, $this->box2024Horizontal['xQ3'] - $this->box2024Horizontal['xQ1']) }}" height="{{ $this->box2024Horizontal['boxH'] }}" fill="#16a34a22" stroke="#16a34a" stroke-width="2" rx="3">
                                <title>Q1–Q3: {{ number_format($this->box2024Horizontal['vQ1'], 1) }} – {{ number_format($this->box2024Horizontal['vQ3'], 1) }}</title>
                            </rect>

                            <!-- median -->
                            <line x1="{{ $this->box2024Horizontal['xMedian'] }}" x2="{{ $this->box2024Horizontal['xMedian'] }}" y1="{{ $this->box2024Horizontal['yMid'] - ($this->box2024Horizontal['boxH']/2) }}" y2="{{ $this->box2024Horizontal['yMid'] + ($this->box2024Horizontal['boxH']/2) }}" stroke="#16a34a" stroke-width="2">
                                <title>Median: {{ number_format($this->box2024Horizontal['vMedian'], 1) }}</title>
                            </line>

                            <!-- numeric labels: min, Q1, median, Q3, max -->
                            <text x="{{ $this->box2024Horizontal['xMin'] }}" y="{{ $this->box2024Horizontal['yMid'] - ($this->box2024Horizontal['boxH']/2) - 6 }}" text-anchor="middle" font-size="10" fill="#374151">{{ number_format($this->box2024Horizontal['vMin'], 1) }}</text>
                            <text x="{{ $this->box2024Horizontal['xQ1'] }}" y="{{ $this->box2024Horizontal['yMid'] + ($this->box2024Horizontal['boxH']/2) + 12 }}" text-anchor="middle" font-size="10" fill="#6b7280">Q1 {{ number_format($this->box2024Horizontal['vQ1'], 1) }}</text>
                            <text x="{{ $this->box2024Horizontal['xMedian'] }}" y="{{ $this->box2024Horizontal['yMid'] - ($this->box2024Horizontal['boxH']/2) - 6 }}" text-anchor="middle" font-size="10" fill="#374151">{{ number_format($this->box2024Horizontal['vMedian'], 1) }}</text>
                            <text x="{{ $this->box2024Horizontal['xQ3'] }}" y="{{ $this->box2024Horizontal['yMid'] + ($this->box2024Horizontal['boxH']/2) + 12 }}" text-anchor="middle" font-size="10" fill="#6b7280">Q3 {{ number_format($this->box2024Horizontal['vQ3'], 1) }}</text>
                            <text x="{{ $this->box2024Horizontal['xMax'] }}" y="{{ $this->box2024Horizontal['yMid'] - ($this->box2024Horizontal['boxH']/2) - 6 }}" text-anchor="middle" font-size="10" fill="#374151">{{ number_format($this->box2024Horizontal['vMax'], 1) }}</text>
                        @endif
                    </svg>
                    <div class="mt-2 text-xs text-muted-foreground">2024 PPR box-and-whisker</div>
                </div>

                <!-- Key numbers -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="text-center">
                        <div class="text-sm text-muted-foreground">Avg Proj PPG</div>
                        <div class="text-2xl font-bold text-green-600">{{ number_format($this->projectionDistribution['avg'] ?? 0, 1) }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm text-muted-foreground">1σ Range</div>
                        <div class="text-2xl font-bold">{{ number_format($this->projectionDistribution['lower'] ?? 0, 1) }} – {{ number_format($this->projectionDistribution['upper'] ?? 0, 1) }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm text-muted-foreground">Season Min/Max (wk)</div>
                        <div class="text-2xl font-bold">{{ number_format($this->projections2025['min_points'] ?? 0, 1) }} / {{ number_format($this->projections2025['max_points'] ?? 0, 1) }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm text-muted-foreground">Median PPG (2024)</div>
                        <div class="text-2xl font-bold text-blue-600">@if(!is_null($this->actualMedian2024)) {{ number_format($this->actualMedian2024, 1) }} @else — @endif</div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex items-center gap-2">
                    <flux:button size="sm" variant="{{ $summaryTab === '2024' ? 'primary' : 'ghost' }}" wire:click="$set('summaryTab','2024')">2024 Results</flux:button>
                    <flux:button size="sm" variant="{{ $summaryTab === '2025' ? 'primary' : 'ghost' }}" wire:click="$set('summaryTab','2025')">2025 Projections</flux:button>
                </div>
            </div>
        </flux:callout>

        <!-- Tab Panels -->
        @if ($summaryTab === '2024')
            @if (!empty($this->stats2024))
                <flux:callout>
                    <flux:heading size="md" class="mb-4">2024 Season Stats</flux:heading>
                    <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-3">
                        @if (isset($this->summary2024['total_points']))
                                <div class="flex justify-between"><span>Total Points:</span><span class="font-semibold">{{ number_format($this->summary2024['total_points'], 1) }}</span></div>
                        @endif
                            @if (isset($this->summary2024['average_points_per_game']) && ($this->summary2024['games_active'] ?? 0) > 0)
                                <div class="flex justify-between"><span>Avg PPG:</span><span class="font-semibold">{{ number_format($this->summary2024['average_points_per_game'], 1) }}</span></div>
                        @endif
                            @if (isset($this->summary2024['min_points']))
                                <div class="flex justify-between"><span>Worst Game:</span><span class="font-semibold">{{ number_format($this->summary2024['min_points'], 1) }}</span></div>
                        @endif
                        @if (isset($this->summary2024['max_points']))
                                <div class="flex justify-between"><span>Best Game:</span><span class="font-semibold">{{ number_format($this->summary2024['max_points'], 1) }}</span></div>
                            @endif
                            </div>
                        <div class="space-y-3">
                            @if (isset($this->summary2024['games_active']))
                                <div class="flex justify-between"><span>Games Played:</span><span class="font-semibold">{{ $this->summary2024['games_active'] }}</span></div>
                            @endif
                            @if (isset($this->summary2024['stddev_below']) && isset($this->summary2024['stddev_above']))
                                <div class="flex justify-between"><span>±1σ PPG:</span><span class="font-semibold">{{ number_format($this->summary2024['stddev_below'], 1) }} – {{ number_format($this->summary2024['stddev_above'], 1) }}</span></div>
                        @endif
                    </div>
                    </div>
                </flux:callout>

                @if ($this->weeklyStats->count() > 0)
                    <flux:callout>
                        <flux:heading size="md" class="mb-4">Weekly Performance (2024)</flux:heading>
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
                                        @php $stats = $weekStat->stats ?? []; @endphp
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
                    <div class="text-center py-8 text-muted-foreground">No 2024 stats available for this player.</div>
                </flux:callout>
            @endif
        @elseif ($summaryTab === '2025')
                <flux:callout>
                    <flux:heading size="md" class="mb-4">2025 Projections</flux:heading>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-3">
                        <div class="flex justify-between"><span>Total Projected Points:</span><span class="font-semibold">{{ number_format($this->projections2025['total_points'] ?? 0, 1) }}</span></div>
                        <div class="flex justify-between"><span>Projected Games:</span><span class="font-semibold">{{ $this->projections2025['games'] ?? 0 }}</span></div>
                        <div class="flex justify-between"><span>Projected PPG:</span><span class="font-semibold">{{ number_format($this->projections2025['average_points_per_game'] ?? 0, 1) }}</span></div>
                            </div>
                    <div class="space-y-3">
                        <div class="flex justify-between"><span>±1σ PPG:</span><span class="font-semibold">{{ number_format($this->projectionDistribution['lower'] ?? 0, 1) }} – {{ number_format($this->projectionDistribution['upper'] ?? 0, 1) }}</span></div>
                        <div class="flex justify-between"><span>Season Min/Max (wk):</span><span class="font-semibold">{{ number_format($this->projections2025['min_points'] ?? 0, 1) }} / {{ number_format($this->projections2025['max_points'] ?? 0, 1) }}</span></div>
                            </div>
                            </div>
            </flux:callout>

            @if ($this->weeklyProjections->count() > 0)
                <flux:callout>
                    <flux:heading size="md" class="mb-4">Weekly Projections (2025)</flux:heading>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b">
                                <tr>
                                    <th class="px-3 py-2 text-left">Week</th>
                                    <th class="px-3 py-2 text-left">Projected PPR</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($this->weeklyProjections->sortBy('week') as $proj)
                                    @php $stats = $proj->stats ?? []; @endphp
                                    <tr>
                                        <td class="px-3 py-2 font-medium">{{ $proj->week }}</td>
                                        <td class="px-3 py-2">@if(isset($stats['pts_ppr'])) {{ number_format($stats['pts_ppr'], 1) }} @elseif(isset($proj->pts_ppr)) {{ number_format($proj->pts_ppr, 1) }} @else - @endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </flux:callout>
            @endif
        @endif

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