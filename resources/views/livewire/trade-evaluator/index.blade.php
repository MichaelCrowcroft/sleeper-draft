<?php

use App\Models\Player;
use Livewire\Volt\Component;

new class extends Component {
    public array $givingPlayers = [];
    public array $receivingPlayers = [];
    public string $searchQuery = '';
    public array $searchResults = [];
    public bool $showSearchResults = false;
    public string $activePanel = 'giving'; // 'giving' or 'receiving'

    public function updatedSearchQuery()
    {
        if (strlen($this->searchQuery) >= 2) {
            $this->searchResults = Player::where('active', true)
                ->whereIn('position', ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'])
                ->where(function ($query) {
                    $query->whereRaw('LOWER(CONCAT(first_name, " ", last_name)) LIKE ?', ['%' . strtolower($this->searchQuery) . '%'])
                        ->orWhereRaw('LOWER(team) LIKE ?', ['%' . strtolower($this->searchQuery) . '%'])
                        ->orWhereRaw('LOWER(position) LIKE ?', ['%' . strtolower($this->searchQuery) . '%']);
                })
                ->limit(10)
                ->get()
                ->map(function ($player) {
                    return [
                        'id' => $player->player_id,
                        'name' => $player->first_name . ' ' . $player->last_name,
                        'position' => $player->position,
                        'team' => $player->team,
                        'in_giving' => in_array($player->player_id, array_column($this->givingPlayers, 'id')),
                        'in_receiving' => in_array($player->player_id, array_column($this->receivingPlayers, 'id')),
                    ];
                })
                ->toArray();

            $this->showSearchResults = true;
        } else {
            $this->searchResults = [];
            $this->showSearchResults = false;
        }
    }

    public function addPlayerToTrade($playerId, $panel)
    {
        $player = Player::where('player_id', $playerId)->first();
        if (!$player) return;

        // Get simple stats
        $projection2025 = $player->getSeason2025ProjectionSummary();
        $stats2024 = $player->getSeason2024Summary();

        $playerData = [
            'id' => $player->player_id,
            'name' => $player->first_name . ' ' . $player->last_name,
            'position' => $player->position,
            'team' => $player->team,
            'proj_ppg' => $projection2025['average_points_per_game'] ?? 0,
            'proj_total' => $projection2025['total_points'] ?? 0,
            'proj_games' => $projection2025['games'] ?? 16,
            'actual_ppg' => $stats2024['average_points_per_game'] ?? 0,
            'actual_total' => is_numeric($stats2024['total_points']) ? $stats2024['total_points'] : 0,
            'actual_games' => $stats2024['games_active'] ?? 0,
        ];

        if ($panel === 'giving') {
            $this->receivingPlayers = array_filter($this->receivingPlayers, fn($p) => $p['id'] !== $playerId);
            if (!in_array($playerId, array_column($this->givingPlayers, 'id'))) {
                $this->givingPlayers[] = $playerData;
            }
        } else {
            $this->givingPlayers = array_filter($this->givingPlayers, fn($p) => $p['id'] !== $playerId);
            if (!in_array($playerId, array_column($this->receivingPlayers, 'id'))) {
                $this->receivingPlayers[] = $playerData;
            }
        }

        $this->searchQuery = '';
        $this->searchResults = [];
        $this->showSearchResults = false;
    }

    public function removePlayerFromTrade($playerId, $panel)
    {
        if ($panel === 'giving') {
            $this->givingPlayers = array_filter($this->givingPlayers, fn($p) => $p['id'] !== $playerId);
        } else {
            $this->receivingPlayers = array_filter($this->receivingPlayers, fn($p) => $p['id'] !== $playerId);
        }
    }

    public function setActivePanel($panel)
    {
        $this->activePanel = $panel;
    }

    public function getGivingTradeSummaryProperty(): array
    {
        return $this->calculateTradeSummary($this->givingPlayers);
    }

    public function getReceivingTradeSummaryProperty(): array
    {
        return $this->calculateTradeSummary($this->receivingPlayers);
    }

    public function getTradeAnalysisProperty(): array
    {
        $giving = $this->givingTradeSummary;
        $receiving = $this->receivingTradeSummary;

        $valueDiff = $receiving['total_value'] - $giving['total_value'];
        $projDiff = $receiving['total_projection'] - $giving['total_projection'];

        return [
            'value_differential' => $valueDiff,
            'projection_differential' => $projDiff,
            'giving_value' => $giving['total_value'],
            'receiving_value' => $receiving['total_value'],
            'recommendation' => $this->getTradeRecommendation($valueDiff, $projDiff),
        ];
    }

    private function calculateTradeSummary(array $players): array
    {
        if (empty($players)) {
            return [
                'total_projection' => 0,
                'total_actual' => 0,
                'total_value' => 0,
                'avg_projection' => 0,
                'avg_actual' => 0,
                'player_count' => 0,
            ];
        }

        $totalProjection = 0;
        $totalActual = 0;
        $totalValue = 0;

        foreach ($players as $player) {
            $projPpg = (float) ($player['proj_ppg'] ?? 0);
            $actualPpg = (float) ($player['actual_ppg'] ?? 0);
            $projGames = (int) ($player['proj_games'] ?? 16);

            // Calculate season totals
            $projTotal = $projPpg * $projGames;
            $actualTotal = (float) ($player['actual_total'] ?? 0);

            $totalProjection += $projTotal;
            $totalActual += $actualTotal;

            // Simple value calculation: 70% projection, 30% historical
            $playerValue = ($projPpg * 0.7) + ($actualPpg * 0.3);
            $totalValue += $playerValue * $projGames;
        }

        $playerCount = count($players);

        return [
            'total_projection' => $totalProjection,
            'total_actual' => $totalActual,
            'total_value' => $totalValue,
            'avg_projection' => $playerCount > 0 ? $totalProjection / $playerCount : 0,
            'avg_actual' => $playerCount > 0 ? $totalActual / $playerCount : 0,
            'player_count' => $playerCount,
        ];
    }

    private function getTradeRecommendation(float $valueDiff, float $projDiff): array
    {
        if ($valueDiff > 20) {
            return ['text' => 'Excellent Trade', 'color' => 'text-green-600', 'icon' => '✓'];
        } elseif ($valueDiff > 10) {
            return ['text' => 'Good Trade', 'color' => 'text-green-500', 'icon' => '✓'];
        } elseif ($valueDiff > 5) {
            return ['text' => 'Fair Trade', 'color' => 'text-blue-600', 'icon' => '→'];
        } elseif ($valueDiff > -5) {
            return ['text' => 'Even Trade', 'color' => 'text-gray-600', 'icon' => '='];
        } elseif ($valueDiff > -10) {
            return ['text' => 'Questionable', 'color' => 'text-orange-600', 'icon' => '⚠'];
        } else {
            return ['text' => 'Poor Trade', 'color' => 'text-red-600', 'icon' => '✗'];
        }
    }
}; ?>

<section class="w-full max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="text-center mb-8">
        <flux:heading size="xl" class="mb-2">Trade Evaluator</flux:heading>
        <p class="text-muted-foreground">Compare players and analyze trade values</p>
    </div>

    <!-- Player Search -->
    <div class="mb-8">
        <flux:callout>
            <div class="space-y-4">
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <flux:input
                            wire:model.live.debounce.300ms="searchQuery"
                            placeholder="Search for players by name, team, or position..."
                            class="w-full"
                        />
                    </div>
                    <div class="flex gap-2">
                        <flux:button
                            wire:click="setActivePanel('giving')"
                            variant="{{ $activePanel === 'giving' ? 'primary' : 'outline' }}"
                            size="sm"
                        >
                            Add to Giving
                        </flux:button>
                        <flux:button
                            wire:click="setActivePanel('receiving')"
                            variant="{{ $activePanel === 'receiving' ? 'primary' : 'outline' }}"
                            size="sm"
                        >
                            Add to Receiving
                        </flux:button>
                    </div>
                </div>

                @if ($showSearchResults && !empty($searchResults))
                    <div class="border rounded-lg max-h-60 overflow-y-auto">
                        @foreach ($searchResults as $player)
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-800 border-b last:border-b-0">
                                <div class="flex items-center gap-3">
                                    <flux:badge variant="outline" size="sm">{{ $player['position'] }}</flux:badge>
                                    <div>
                                        <div class="font-medium">{{ $player['name'] }}</div>
                                        <div class="text-sm text-muted-foreground">{{ $player['team'] }}</div>
                                    </div>
                                </div>
                                <div>
                                    @if (!$player['in_giving'] && !$player['in_receiving'])
                                        <flux:button
                                            wire:click="addPlayerToTrade('{{ $player['id'] }}', '{{ $activePanel }}')"
                                            variant="ghost"
                                            size="sm"
                                        >
                                            Add to {{ ucfirst($activePanel) }}
                                        </flux:button>
                                    @else
                                        <span class="text-xs text-muted-foreground">
                                            @if ($player['in_giving']) In Giving @else In Receiving @endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </flux:callout>
    </div>

    <!-- Trade Analysis Overview -->
    @if (!empty($givingPlayers) || !empty($receivingPlayers))
        <div class="mb-8">
            <flux:callout>
                <flux:heading size="md" class="mb-4">Trade Analysis</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Value Differential -->
                    <div class="text-center">
                        <div class="text-2xl font-bold {{ $this->tradeAnalysis['value_differential'] > 0 ? 'text-green-600' : ($this->tradeAnalysis['value_differential'] < 0 ? 'text-red-600' : 'text-gray-600') }}">
                            {{ $this->tradeAnalysis['value_differential'] > 0 ? '+' : '' }}{{ number_format($this->tradeAnalysis['value_differential'], 1) }}
                        </div>
                        <div class="text-sm text-muted-foreground">Value Differential</div>
                    </div>

                    <!-- Projection Differential -->
                    <div class="text-center">
                        <div class="text-2xl font-bold {{ $this->tradeAnalysis['projection_differential'] > 0 ? 'text-green-600' : ($this->tradeAnalysis['projection_differential'] < 0 ? 'text-red-600' : 'text-gray-600') }}">
                            {{ $this->tradeAnalysis['projection_differential'] > 0 ? '+' : '' }}{{ number_format($this->tradeAnalysis['projection_differential'], 1) }}
                        </div>
                        <div class="text-sm text-muted-foreground">Projection Differential</div>
                    </div>

                    <!-- Recommendation -->
                    <div class="text-center">
                        <div class="text-lg font-semibold {{ $this->tradeAnalysis['recommendation']['color'] }}">
                            {{ $this->tradeAnalysis['recommendation']['icon'] }} {{ $this->tradeAnalysis['recommendation']['text'] }}
                        </div>
                        <div class="text-sm text-muted-foreground">Recommendation</div>
                    </div>
                </div>
            </flux:callout>
        </div>
    @endif

    <!-- Trade Panels -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Giving Panel -->
        <div>
            <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg" class="text-red-800 dark:text-red-200">Giving Up</flux:heading>
                    <flux:badge variant="destructive">{{ count($givingPlayers) }} players</flux:badge>
                </div>

                @if (empty($givingPlayers))
                    <div class="text-center py-8 text-muted-foreground">
                        <p>Add players you're giving up in the trade</p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($givingPlayers as $player)
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <flux:badge variant="primary" size="sm">{{ $player['position'] }}</flux:badge>
                                        <div>
                                            <div class="font-medium">{{ $player['name'] }}</div>
                                            <div class="text-sm text-muted-foreground">{{ $player['team'] }}</div>
                                        </div>
                                    </div>
                                    <flux:button
                                        wire:click="removePlayerFromTrade('{{ $player['id'] }}', 'giving')"
                                        variant="ghost"
                                        size="sm"
                                        class="text-red-600 hover:text-red-700"
                                    >
                                        Remove
                                    </flux:button>
                                </div>

                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-muted-foreground">2025 Proj:</span>
                                        <span class="font-medium">{{ number_format($player['proj_ppg'] ?? 0, 1) }} PPG</span>
                                    </div>
                                    <div>
                                        <span class="text-muted-foreground">2024 Avg:</span>
                                        <span class="font-medium">{{ number_format($player['actual_ppg'] ?? 0, 1) }} PPG</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <!-- Giving Summary -->
                        <div class="bg-red-100 dark:bg-red-900/30 rounded-lg p-4 border border-red-200 dark:border-red-800">
                            <div class="text-sm font-medium text-red-800 dark:text-red-200 mb-2">Trade Summary</div>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Total Projection:</span>
                                    <span class="font-medium">{{ number_format($this->givingTradeSummary['total_projection'], 1) }} pts</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Total Value:</span>
                                    <span class="font-medium">{{ number_format($this->givingTradeSummary['total_value'], 1) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Players:</span>
                                    <span class="font-medium">{{ $this->givingTradeSummary['player_count'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Receiving Panel -->
        <div>
            <div class="bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg" class="text-green-800 dark:text-green-200">Receiving</flux:heading>
                    <flux:badge variant="primary">{{ count($receivingPlayers) }} players</flux:badge>
                </div>

                @if (empty($receivingPlayers))
                    <div class="text-center py-8 text-muted-foreground">
                        <p>Add players you're receiving in the trade</p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($receivingPlayers as $player)
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <flux:badge variant="primary" size="sm">{{ $player['position'] }}</flux:badge>
                                        <div>
                                            <div class="font-medium">{{ $player['name'] }}</div>
                                            <div class="text-sm text-muted-foreground">{{ $player['team'] }}</div>
                                        </div>
                                    </div>
                                    <flux:button
                                        wire:click="removePlayerFromTrade('{{ $player['id'] }}', 'receiving')"
                                        variant="ghost"
                                        size="sm"
                                        class="text-red-600 hover:text-red-700"
                                    >
                                        Remove
                                    </flux:button>
                                </div>

                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-muted-foreground">2025 Proj:</span>
                                        <span class="font-medium">{{ number_format($player['proj_ppg'] ?? 0, 1) }} PPG</span>
                                    </div>
                                    <div>
                                        <span class="text-muted-foreground">2024 Avg:</span>
                                        <span class="font-medium">{{ number_format($player['actual_ppg'] ?? 0, 1) }} PPG</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <!-- Receiving Summary -->
                        <div class="bg-green-100 dark:bg-green-900/30 rounded-lg p-4 border border-green-200 dark:border-green-800">
                            <div class="text-sm font-medium text-green-800 dark:text-green-200 mb-2">Trade Summary</div>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Total Projection:</span>
                                    <span class="font-medium">{{ number_format($this->receivingTradeSummary['total_projection'], 1) }} pts</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Total Value:</span>
                                    <span class="font-medium">{{ number_format($this->receivingTradeSummary['total_value'], 1) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Players:</span>
                                    <span class="font-medium">{{ $this->receivingTradeSummary['player_count'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Detailed Comparison -->
    @if (!empty($givingPlayers) && !empty($receivingPlayers))
        <div class="mt-8">
            <flux:callout>
                <flux:heading size="md" class="mb-6">Trade Comparison</flux:heading>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Projection Comparison -->
                    <div>
                        <flux:heading size="sm" class="mb-4">2025 Projections</flux:heading>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                <span class="font-medium">Giving Total:</span>
                                <span class="text-red-600 font-bold">{{ number_format($this->givingTradeSummary['total_projection'], 1) }} pts</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <span class="font-medium">Receiving Total:</span>
                                <span class="text-green-600 font-bold">{{ number_format($this->receivingTradeSummary['total_projection'], 1) }} pts</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                <span class="font-medium">Net Gain:</span>
                                <span class="font-bold {{ $this->tradeAnalysis['projection_differential'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $this->tradeAnalysis['projection_differential'] > 0 ? '+' : '' }}{{ number_format($this->tradeAnalysis['projection_differential'], 1) }} pts
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Value Comparison -->
                    <div>
                        <flux:heading size="sm" class="mb-4">Trade Value</flux:heading>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                <span class="font-medium">Giving Value:</span>
                                <span class="text-red-600 font-bold">{{ number_format($this->givingTradeSummary['total_value'], 1) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <span class="font-medium">Receiving Value:</span>
                                <span class="text-green-600 font-bold">{{ number_format($this->receivingTradeSummary['total_value'], 1) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                <span class="font-medium">Net Value:</span>
                                <span class="font-bold {{ $this->tradeAnalysis['value_differential'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $this->tradeAnalysis['value_differential'] > 0 ? '+' : '' }}{{ number_format($this->tradeAnalysis['value_differential'], 1) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trade Insights -->
                <div class="mt-8">
                    <flux:heading size="sm" class="mb-4">Trade Insights</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @php
                            $valueDiff = $this->tradeAnalysis['value_differential'];
                            $projDiff = $this->tradeAnalysis['projection_differential'];
                        @endphp

                        @if ($valueDiff > 10)
                            <div class="p-4 bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <span class="text-green-600 text-xl">✓</span>
                                    <div>
                                        <div class="font-medium text-green-800 dark:text-green-200">Strong Value Gain</div>
                                        <div class="text-sm text-green-700 dark:text-green-300">You're gaining significant value in this trade</div>
                                    </div>
                                </div>
                            </div>
                        @elseif ($valueDiff < -10)
                            <div class="p-4 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <span class="text-red-600 text-xl">⚠</span>
                                    <div>
                                        <div class="font-medium text-red-800 dark:text-red-200">Value Loss</div>
                                        <div class="text-sm text-red-700 dark:text-red-300">Consider if this trade is worth the value you're giving up</div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($projDiff > 20)
                            <div class="p-4 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <span class="text-blue-600 text-xl">📈</span>
                                    <div>
                                        <div class="font-medium text-blue-800 dark:text-blue-200">Projection Upgrade</div>
                                        <div class="text-sm text-blue-700 dark:text-blue-300">Significant improvement in projected points</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </flux:callout>
        </div>
    @endif
</section>