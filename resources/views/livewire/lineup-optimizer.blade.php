<?php

use App\Actions\Matchups\AssembleMatchupViewModel;
use App\Actions\Matchups\ComputeWinProbability;
use App\Actions\Matchups\OptimizeLineup;
use App\Actions\Sleeper\FetchLeague;
use App\Actions\Sleeper\FetchRosters;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $leagueId;
    public ?int $week = null;
    public ?int $rosterId = null;

    // Interactive lineup management
    public array $currentStarters = [];
    public array $currentBench = [];
    public array $optimizedStarters = [];
    public array $optimizedBench = [];

    // UI state
    public bool $showOptimized = true;
    public ?string $draggedPlayer = null;
    public ?string $dragSource = null;
    public array $highlightedPositions = [];

    public function mount(string $leagueId, ?int $week = null, ?int $rosterId = null): void
    {
        $this->leagueId = $leagueId;
        $this->week = $week;
        $this->rosterId = $rosterId;

        $this->loadLineupData();
    }

    public function loadLineupData(): void
    {
        $vm = app(AssembleMatchupViewModel::class)->execute($this->leagueId, $this->week, $this->rosterId);

        if (!isset($vm['home']['lineup_optimization'])) {
            return;
        }

        $optimization = $vm['home']['lineup_optimization'];

        // Initialize current lineup from view model
        $this->currentStarters = $vm['home']['starters'];
        $this->currentBench = array_diff($vm['home']['players'] ?? [], $this->currentStarters) ?: [];

        // Set optimized lineup
        $this->optimizedStarters = array_keys($optimization['recommendations'] ?? []);
        $this->optimizedBench = array_diff(array_keys($vm['home']['players'] ?? []), $this->optimizedStarters) ?: [];
    }

    public function toggleOptimized(): void
    {
        $this->showOptimized = !$this->showOptimized;
    }

    public function startDrag($playerId, $source): void
    {
        $this->draggedPlayer = $playerId;
        $this->dragSource = $source;
    }

    public function dropPlayer($targetPosition, $targetType): void
    {
        if (!$this->draggedPlayer || !$this->dragSource) {
            return;
        }

        $sourceType = $this->dragSource;
        $playerId = $this->draggedPlayer;

        // Remove from source
        if ($sourceType === 'current-starters') {
            $this->currentStarters = array_diff($this->currentStarters, [$playerId]);
        } elseif ($sourceType === 'current-bench') {
            $this->currentBench = array_diff($this->currentBench, [$playerId]);
        } elseif ($sourceType === 'optimized-starters') {
            $this->optimizedStarters = array_diff($this->optimizedStarters, [$playerId]);
        } elseif ($sourceType === 'optimized-bench') {
            $this->optimizedBench = array_diff($this->optimizedBench, [$playerId]);
        }

        // Add to target
        if ($targetType === 'current-starters') {
            $this->currentStarters[] = $playerId;
        } elseif ($targetType === 'current-bench') {
            $this->currentBench[] = $playerId;
        } elseif ($targetType === 'optimized-starters') {
            $this->optimizedStarters[] = $playerId;
        } elseif ($targetType === 'optimized-bench') {
            $this->optimizedBench[] = $playerId;
        }

        // Clean up arrays
        $this->currentStarters = array_values(array_unique($this->currentStarters));
        $this->currentBench = array_values(array_unique($this->currentBench));
        $this->optimizedStarters = array_values(array_unique($this->optimizedStarters));
        $this->optimizedBench = array_values(array_unique($this->optimizedBench));

        $this->draggedPlayer = null;
        $this->dragSource = null;
        $this->highlightedPositions = [];
    }

    public function swapPlayers($player1, $player2, $source1, $source2): void
    {
        // Remove players from their current positions
        if ($source1 === 'current-starters') {
            $this->currentStarters = array_diff($this->currentStarters, [$player1]);
        } elseif ($source1 === 'current-bench') {
            $this->currentBench = array_diff($this->currentBench, [$player1]);
        } elseif ($source1 === 'optimized-starters') {
            $this->optimizedStarters = array_diff($this->optimizedStarters, [$player1]);
        } elseif ($source1 === 'optimized-bench') {
            $this->optimizedBench = array_diff($this->optimizedBench, [$player1]);
        }

        if ($source2 === 'current-starters') {
            $this->currentStarters = array_diff($this->currentStarters, [$player2]);
        } elseif ($source2 === 'current-bench') {
            $this->currentBench = array_diff($this->currentBench, [$player2]);
        } elseif ($source2 === 'optimized-starters') {
            $this->optimizedStarters = array_diff($this->optimizedStarters, [$player2]);
        } elseif ($source2 === 'optimized-bench') {
            $this->optimizedBench = array_diff($this->optimizedBench, [$player2]);
        }

        // Add players to swapped positions
        if ($source1 === 'current-starters') {
            $this->currentStarters[] = $player2;
        } elseif ($source1 === 'current-bench') {
            $this->currentBench[] = $player2;
        } elseif ($source1 === 'optimized-starters') {
            $this->optimizedStarters[] = $player2;
        } elseif ($source1 === 'optimized-bench') {
            $this->optimizedBench[] = $player2;
        }

        if ($source2 === 'current-starters') {
            $this->currentStarters[] = $player1;
        } elseif ($source2 === 'current-bench') {
            $this->currentBench[] = $player1;
        } elseif ($source2 === 'optimized-starters') {
            $this->optimizedStarters[] = $player1;
        } elseif ($source2 === 'optimized-bench') {
            $this->optimizedBench[] = $player1;
        }

        // Clean up arrays
        $this->currentStarters = array_values(array_unique($this->currentStarters));
        $this->currentBench = array_values(array_unique($this->currentBench));
        $this->optimizedStarters = array_values(array_unique($this->optimizedStarters));
        $this->optimizedBench = array_values(array_unique($this->optimizedBench));
    }

    public function applyOptimizedLineup(): void
    {
        $this->currentStarters = $this->optimizedStarters;
        $this->currentBench = $this->optimizedBench;
    }

    public function resetToOriginal(): void
    {
        $this->loadLineupData();
    }

    public function getModelProperty(): array
    {
        $vm = app(AssembleMatchupViewModel::class)->execute($this->leagueId, $this->week, $this->rosterId);

        if (!isset($vm['home'])) {
            return $vm;
        }

        $home = $vm['home'];

        // Calculate current lineup projections
        $currentTotal = $this->calculateLineupTotal($this->currentStarters, $home['points']);
        $optimizedTotal = $this->calculateLineupTotal($this->optimizedStarters, $home['points']);

        // Calculate win probabilities
        $currentWp = $this->calculateWinProbability($currentTotal, $vm['away']['totals']['total_estimated']);
        $optimizedWp = $this->calculateWinProbability($optimizedTotal, $vm['away']['totals']['total_estimated']);

        return array_merge($vm, [
            'current_lineup_total' => $currentTotal,
            'optimized_lineup_total' => $optimizedTotal,
            'current_win_probability' => $currentWp,
            'optimized_win_probability' => $optimizedWp,
            'improvement' => $optimizedTotal - $currentTotal,
            'win_probability_improvement' => $optimizedWp['home'] - $currentWp['home'],
        ]);
    }

    private function calculateLineupTotal(array $starters, array $points): float
    {
        $total = 0.0;
        foreach ($starters as $playerId) {
            $total += $points[$playerId]['used'] ?? 0.0;
        }
        return round($total, 1);
    }

    private function calculateWinProbability(float $homeTotal, float $awayTotal): array
    {
        $meanDiff = $homeTotal - $awayTotal;
        $variance = 36.0; // Simplified variance
        $std = sqrt($variance * 2);

        $z = $meanDiff / $std;
        $homeProb = 0.5 * (1.0 + $this->erf($z / sqrt(2.0)));
        $homeProb = max(0.0, min(1.0, $homeProb));

        return [
            'home' => round($homeProb, 4),
            'away' => round(1.0 - $homeProb, 4),
        ];
    }

    private function erf(float $x): float
    {
        $sign = $x < 0 ? -1.0 : 1.0;
        $x = abs($x);

        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;
        $p = 0.3275911;

        $t = 1.0 / (1.0 + $p * $x);
        $y = 1.0 - (((((
            $a5 * $t + $a4) * $t + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$x * $x));

        return $sign * $y;
    }

    public function getPlayerInfo($playerId): array
    {
        $vm = app(AssembleMatchupViewModel::class)->execute($this->leagueId, $this->week, $this->rosterId);
        return $vm['home']['players'][$playerId] ?? [
            'name' => $playerId,
            'position' => 'UNK',
            'team' => null,
        ];
    }

    public function getPlayerPoints($playerId): array
    {
        $vm = app(AssembleMatchupViewModel::class)->execute($this->leagueId, $this->week, $this->rosterId);
        return $vm['home']['points'][$playerId] ?? [
            'actual' => 0.0,
            'projected' => 0.0,
            'used' => 0.0,
            'status' => 'upcoming',
        ];
    }
}; ?>

<div class="min-h-screen bg-background">
    <!-- Header -->
    <div class="border-b bg-card">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Lineup Optimizer</h1>
                    <p class="text-muted-foreground">Interactive lineup optimization with real-time projections</p>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button wire:click="resetToOriginal" variant="outline" size="sm">
                        Reset
                    </flux:button>
                    <flux:button wire:click="applyOptimizedLineup" variant="primary" size="sm">
                        Apply Optimized
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-6">
        @if (isset($this->model['error']))
            <flux:callout variant="danger">{{ $this->model['error'] }}</flux:callout>
        @else
            <!-- League Info -->
            <div class="mb-6">
                <flux:callout>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-semibold">{{ $this->model['home']['owner_name'] ?? 'Your Team' }}</div>
                            <div class="text-sm text-muted-foreground">
                                {{ $this->model['league']['name'] }} • Week {{ $this->model['week'] }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold">vs {{ $this->model['away']['owner_name'] ?? 'Opponent' }}</div>
                            <div class="text-sm text-muted-foreground">{{ number_format($this->model['away']['totals']['total_estimated'], 1) }} pts projected</div>
                        </div>
                    </div>
                </flux:callout>
            </div>

            <!-- Projection Comparison -->
                    <div class="grid gap-4 md:grid-cols-3 mb-6">
                        <flux:callout>
                            <div class="text-center">
                                <div class="text-sm text-muted-foreground">Current Lineup</div>
                                <div class="text-2xl font-bold text-blue-600">{{ number_format($this->model['current_lineup_total'], 1) }}</div>
                                <div class="text-xs text-muted-foreground">{{ (int) round($this->model['current_win_probability']['home'] * 100) }}% win prob</div>
                            </div>
                        </flux:callout>

                        <flux:callout>
                            <div class="text-center">
                                <div class="text-sm text-muted-foreground">Optimized Lineup</div>
                                <div class="text-2xl font-bold text-green-600">{{ number_format($this->model['optimized_lineup_total'], 1) }}</div>
                                <div class="text-xs text-muted-foreground">{{ (int) round($this->model['optimized_win_probability']['home'] * 100) }}% win prob</div>
                            </div>
                        </flux:callout>

                        <flux:callout>
                            <div class="text-center">
                                <div class="text-sm text-muted-foreground">Improvement</div>
                                <div class="text-2xl font-bold {{ $this->model['improvement'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $this->model['improvement'] > 0 ? '+' : '' }}{{ number_format($this->model['improvement'], 1) }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ $this->model['win_probability_improvement'] > 0 ? '+' : '' }}{{ number_format($this->model['win_probability_improvement'] * 100, 1) }}% win prob
                                </div>
                            </div>
                        </flux:callout>
                    </div>

            <!-- Interactive Lineup Builder -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Current Lineup -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Current Lineup</h2>
                        <span class="text-sm text-muted-foreground">{{ count($this->currentStarters) }} starters</span>
                    </div>

                    <!-- Starters -->
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-muted-foreground">Starters</div>
                        <div class="space-y-2 min-h-[200px] border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg p-4"
                             ondragover="event.preventDefault()"
                             wire:drop="dropPlayer('starters', 'current-starters')">
                            @forelse ($this->currentStarters as $playerId)
                                @php
                                    $player = $this->getPlayerInfo($playerId);
                                    $points = $this->getPlayerPoints($playerId);
                                @endphp
                                <div class="flex items-center justify-between p-3 bg-card border rounded-lg cursor-move hover:bg-accent transition-colors"
                                     draggable="true"
                                     wire:drag.start="startDrag('{{ $playerId }}', 'current-starters')">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-primary/10 rounded flex items-center justify-center text-xs font-medium">
                                            {{ $player['position'] }}
                                        </div>
                                        <div>
                                            <div class="font-medium">{{ $player['name'] }}</div>
                                            <div class="text-xs text-muted-foreground">{{ $player['team'] }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium">{{ number_format($points['used'], 1) }}</div>
                                        <div class="text-xs {{ $points['status'] === 'locked' ? 'text-emerald-600' : 'text-yellow-600' }}">
                                            {{ ucfirst($points['status']) }}
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted-foreground py-8">
                                    Drop players here to start them
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Bench -->
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-muted-foreground">Bench</div>
                        <div class="space-y-2 min-h-[150px] border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg p-4"
                             ondragover="event.preventDefault()"
                             wire:drop="dropPlayer('bench', 'current-bench')">
                            @forelse ($this->currentBench as $playerId)
                                @php
                                    $player = $this->getPlayerInfo($playerId);
                                    $points = $this->getPlayerPoints($playerId);
                                @endphp
                                <div class="flex items-center justify-between p-2 bg-muted/50 border rounded cursor-move hover:bg-accent transition-colors"
                                     draggable="true"
                                     wire:drag.start="startDrag('{{ $playerId }}', 'current-bench')">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 bg-primary/10 rounded flex items-center justify-center text-xs">
                                            {{ $player['position'] }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium">{{ $player['name'] }}</div>
                                            <div class="text-xs text-muted-foreground">{{ $player['team'] }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm">{{ number_format($points['used'], 1) }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted-foreground py-4">
                                    Drop players here to bench them
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Optimized Lineup -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Optimized Lineup</h2>
                        <div class="flex items-center gap-2">
                            <flux:button wire:click="toggleOptimized" variant="ghost" size="sm">
                                {{ $this->showOptimized ? 'Hide' : 'Show' }} Optimized
                            </flux:button>
                            <span class="text-sm text-muted-foreground">{{ count($this->optimizedStarters) }} starters</span>
                        </div>
                    </div>

                    @if ($this->showOptimized)
                        <!-- Starters -->
                        <div class="space-y-2">
                            <div class="text-sm font-medium text-muted-foreground">Starters</div>
                            <div class="space-y-2 min-h-[200px] border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg p-4"
                                 ondragover="event.preventDefault()"
                                 wire:drop="dropPlayer('starters', 'optimized-starters')">
                                @forelse ($this->optimizedStarters as $playerId)
                                    @php
                                        $player = $this->getPlayerInfo($playerId);
                                        $points = $this->getPlayerPoints($playerId);
                                        $isCurrentStarter = in_array($playerId, $this->currentStarters);
                                    @endphp
                                    <div class="flex items-center justify-between p-3 bg-card border rounded-lg cursor-move hover:bg-accent transition-colors {{ !$isCurrentStarter ? 'ring-2 ring-green-200 dark:ring-green-800' : '' }}"
                                         draggable="true"
                                         wire:drag.start="startDrag('{{ $playerId }}', 'optimized-starters')">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-primary/10 rounded flex items-center justify-center text-xs font-medium">
                                                {{ $player['position'] }}
                                            </div>
                                            <div>
                                                <div class="font-medium">{{ $player['name'] }}</div>
                                                <div class="text-xs text-muted-foreground">{{ $player['team'] }}</div>
                                            </div>
                                            @if (!$isCurrentStarter)
                                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">NEW</span>
                                            @endif
                                        </div>
                                        <div class="text-right">
                                            <div class="font-medium">{{ number_format($points['used'], 1) }}</div>
                                            <div class="text-xs {{ $points['status'] === 'locked' ? 'text-emerald-600' : 'text-yellow-600' }}">
                                                {{ ucfirst($points['status']) }}
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center text-muted-foreground py-8">
                                        Drop players here to start them
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <!-- Bench -->
                        <div class="space-y-2">
                            <div class="text-sm font-medium text-muted-foreground">Bench</div>
                            <div class="space-y-2 min-h-[150px] border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg p-4"
                                 ondragover="event.preventDefault()"
                                 wire:drop="dropPlayer('bench', 'optimized-bench')">
                                @forelse ($this->optimizedBench as $playerId)
                                    @php
                                        $player = $this->getPlayerInfo($playerId);
                                        $points = $this->getPlayerPoints($playerId);
                                    @endphp
                                    <div class="flex items-center justify-between p-2 bg-muted/50 border rounded cursor-move hover:bg-accent transition-colors"
                                         draggable="true"
                                         wire:drag.start="startDrag('{{ $playerId }}', 'optimized-bench')">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 bg-primary/10 rounded flex items-center justify-center text-xs">
                                                {{ $player['position'] }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium">{{ $player['name'] }}</div>
                                                <div class="text-xs text-muted-foreground">{{ $player['team'] }}</div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm">{{ number_format($points['used'], 1) }}</div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center text-muted-foreground py-4">
                                        Drop players here to bench them
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <div class="text-center text-muted-foreground py-16">
                            <div class="text-lg mb-2">Optimized lineup hidden</div>
                            <div class="text-sm">Click "Show Optimized" to view recommendations</div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Instructions -->
            <div class="mt-8">
                <flux:callout>
                    <div class="space-y-2">
                        <div class="font-medium">How to use:</div>
                        <ul class="text-sm text-muted-foreground space-y-1 ml-4">
                            <li>• Drag and drop players between starters and bench</li>
                            <li>• Click "Apply Optimized" to replace your current lineup with the optimized one</li>
                            <li>• Green border indicates recommended changes from current lineup</li>
                            <li>• Win probability updates in real-time as you make changes</li>
                        </ul>
                    </div>
                </flux:callout>
            </div>
        @endif
    </div>
</div>
