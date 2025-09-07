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

    public function mount()
    {
        // Initialize empty trade sides
    }

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
        $player = Player::find($playerId);

        if (!$player) return;

        $playerData = [
            'id' => $player->player_id,
            'name' => $player->first_name . ' ' . $player->last_name,
            'position' => $player->position,
            'team' => $player->team,
            'projection_2025' => $player->getSeason2025ProjectionSummary(),
            'stats_2024' => $player->getSeason2024Summary(),
            'volatility' => $player->getVolatilityMetrics(),
        ];

        if ($panel === 'giving') {
            // Remove from receiving if present
            $this->receivingPlayers = array_filter($this->receivingPlayers, fn($p) => $p['id'] !== $playerId);

            // Add to giving if not already present
            if (!in_array($playerId, array_column($this->givingPlayers, 'id'))) {
                $this->givingPlayers[] = $playerData;
            }
        } else {
            // Remove from giving if present
            $this->givingPlayers = array_filter($this->givingPlayers, fn($p) => $p['id'] !== $playerId);

            // Add to receiving if not already present
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

        return [
            'giving_value' => $giving['total_value'] ?? 0,
            'receiving_value' => $receiving['total_value'] ?? 0,
            'trade_value' => ($receiving['total_value'] ?? 0) - ($giving['total_value'] ?? 0),
            'giving_risk' => $giving['risk_score'] ?? 0,
            'receiving_risk' => $receiving['risk_score'] ?? 0,
            'risk_differential' => ($receiving['risk_score'] ?? 0) - ($giving['risk_score'] ?? 0),
        ];
    }

    private function calculateTradeSummary(array $players): array
    {
        if (empty($players)) {
            return [
                'total_projection' => 0,
                'total_points_2024' => 0,
                'average_projection' => 0,
                'total_value' => 0,
                'risk_score' => 0,
                'boom_potential' => 0,
                'bust_risk' => 0,
                'consistency_score' => 0,
            ];
        }

        $totalProjection = 0;
        $totalPoints2024 = 0;
        $totalValue = 0;
        $totalRisk = 0;
        $boomPotential = 0;
        $bustRisk = 0;
        $consistencyScore = 0;

        foreach ($players as $player) {
            // Projections (weighted heavily for future value)
            $proj = $player['projection_2025']['average_points_per_game'] ?? 0;
            $games = $player['projection_2025']['games'] ?? 16;
            $totalProjection += $proj * $games;

            // Historical performance (weighted for reliability)
            $historical = $player['stats_2024']['average_points_per_game'] ?? 0;
            $gamesPlayed = $player['stats_2024']['games_active'] ?? 16;
            $totalPoints2024 += $historical * min($gamesPlayed, 16); // Cap at 16 games

            // Calculate player value (blend of projection and historical performance)
            $value = ($proj * 0.7) + ($historical * 0.3);
            $totalValue += $value * $games;

            // Risk assessment based on volatility
            $volatility = $player['volatility'];
            $steadiness = $volatility['steadiness_score'] ?? 1.0;
            $boomRate = $volatility['boom_rate'] ?? 0;
            $bustRate = $volatility['bust_rate'] ?? 0;
            $consistency = $volatility['consistency_rate'] ?? 50;

            $totalRisk += (1 / max($steadiness, 0.1)); // Higher risk for lower steadiness
            $boomPotential += $boomRate;
            $bustRisk += $bustRate;
            $consistencyScore += $consistency;
        }

        $playerCount = count($players);

        return [
            'total_projection' => $totalProjection,
            'total_points_2024' => $totalPoints2024,
            'average_projection' => $totalProjection / max($playerCount, 1),
            'total_value' => $totalValue,
            'risk_score' => $totalRisk / max($playerCount, 1),
            'boom_potential' => $boomPotential / max($playerCount, 1),
            'bust_risk' => $bustRisk / max($playerCount, 1),
            'consistency_score' => $consistencyScore / max($playerCount, 1),
        ];
    }
}; ?>

<section class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="text-center mb-8">
        <flux:heading size="xl" class="mb-2">Trade Evaluator</flux:heading>
        <p class="text-muted-foreground">Compare players and analyze trade values to make informed decisions</p>
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
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ $player['name'] }}</span>
                                        <span class="text-sm text-muted-foreground">{{ $player['position'] }} • {{ $player['team'] }}</span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
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
                    <!-- Trade Value -->
                    <div class="text-center">
                        <div class="text-2xl font-bold {{ $this->tradeAnalysis['trade_value'] > 0 ? 'text-green-600' : ($this->tradeAnalysis['trade_value'] < 0 ? 'text-red-600' : 'text-gray-600') }}">
                            {{ $this->tradeAnalysis['trade_value'] > 0 ? '+' : '' }}{{ number_format($this->tradeAnalysis['trade_value'], 1) }}
                        </div>
                        <div class="text-sm text-muted-foreground">Trade Value Differential</div>
                    </div>

                    <!-- Risk Differential -->
                    <div class="text-center">
                        <div class="text-2xl font-bold {{ $this->tradeAnalysis['risk_differential'] < 0 ? 'text-green-600' : ($this->tradeAnalysis['risk_differential'] > 0 ? 'text-red-600' : 'text-gray-600') }}">
                            {{ $this->tradeAnalysis['risk_differential'] > 0 ? '+' : '' }}{{ number_format($this->tradeAnalysis['risk_differential'], 2) }}
                        </div>
                        <div class="text-sm text-muted-foreground">Risk Differential</div>
                    </div>

                    <!-- Recommendation -->
                    <div class="text-center">
                        <div class="text-lg font-semibold">
                            @if ($this->tradeAnalysis['trade_value'] > 5)
                                <span class="text-green-600">Strong Trade</span>
                            @elseif ($this->tradeAnalysis['trade_value'] > 2)
                                <span class="text-blue-600">Fair Trade</span>
                            @elseif ($this->tradeAnalysis['trade_value'] < -5)
                                <span class="text-red-600">Poor Trade</span>
                            @else
                                <span class="text-gray-600">Even Trade</span>
                            @endif
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
        <div class="space-y-6">
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
                                        <span class="font-medium">{{ number_format($player['projection_2025']['average_points_per_game'] ?? 0, 1) }} PPG</span>
                                    </div>
                                    <div>
                                        <span class="text-muted-foreground">2024 Avg:</span>
                                        <span class="font-medium">{{ number_format($player['stats_2024']['average_points_per_game'] ?? 0, 1) }} PPG</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <!-- Giving Summary -->
                        <div class="bg-red-100 dark:bg-red-900/30 rounded-lg p-4 border border-red-200 dark:border-red-800">
                            <div class="text-sm font-medium text-red-800 dark:text-red-200 mb-2">Trade Summary</div>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-muted-foreground">Total Value:</span>
                                    <span class="font-medium">{{ number_format($this->givingTradeSummary['total_value'], 1) }}</span>
                                </div>
                                <div>
                                    <span class="text-muted-foreground">Risk Score:</span>
                                    <span class="font-medium">{{ number_format($this->givingTradeSummary['risk_score'], 2) }}</span>
                                </div>
                                <div>
                                    <span class="text-muted-foreground">Boom Potential:</span>
                                    <span class="font-medium">{{ number_format($this->givingTradeSummary['boom_potential'], 1) }}%</span>
                                </div>
                                <div>
                                    <span class="text-muted-foreground">Bust Risk:</span>
                                    <span class="font-medium">{{ number_format($this->givingTradeSummary['bust_risk'], 1) }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Receiving Panel -->
        <div class="space-y-6">
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
                                        <span class="font-medium">{{ number_format($player['projection_2025']['average_points_per_game'] ?? 0, 1) }} PPG</span>
                                    </div>
                                    <div>
                                        <span class="text-muted-foreground">2024 Avg:</span>
                                        <span class="font-medium">{{ number_format($player['stats_2024']['average_points_per_game'] ?? 0, 1) }} PPG</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <!-- Receiving Summary -->
                        <div class="bg-green-100 dark:bg-green-900/30 rounded-lg p-4 border border-green-200 dark:border-green-800">
                            <div class="text-sm font-medium text-green-800 dark:text-green-200 mb-2">Trade Summary</div>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-muted-foreground">Total Value:</span>
                                    <span class="font-medium">{{ number_format($this->receivingTradeSummary['total_value'], 1) }}</span>
                                </div>
                                <div>
                                    <span class="text-muted-foreground">Risk Score:</span>
                                    <span class="font-medium">{{ number_format($this->receivingTradeSummary['risk_score'], 2) }}</span>
                                </div>
                                <div>
                                    <span class="text-muted-foreground">Boom Potential:</span>
                                    <span class="font-medium">{{ number_format($this->receivingTradeSummary['boom_potential'], 1) }}%</span>
                                </div>
                                <div>
                                    <span class="text-muted-foreground">Bust Risk:</span>
                                    <span class="font-medium">{{ number_format($this->receivingTradeSummary['bust_risk'], 1) }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Detailed Analysis -->
    @if (!empty($givingPlayers) && !empty($receivingPlayers))
        <div class="mt-8">
            <flux:callout>
                <flux:heading size="md" class="mb-6">Detailed Trade Analysis</flux:heading>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Projection Comparison -->
                    <div>
                        <flux:heading size="sm" class="mb-4">Projection Comparison</flux:heading>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <span class="font-medium">Giving Total Projection:</span>
                                <span class="text-red-600 font-bold">{{ number_format($this->givingTradeSummary['total_projection'], 1) }} pts</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <span class="font-medium">Receiving Total Projection:</span>
                                <span class="text-green-600 font-bold">{{ number_format($this->receivingTradeSummary['total_projection'], 1) }} pts</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                <span class="font-medium">Projection Differential:</span>
                                <span class="font-bold {{ ($this->receivingTradeSummary['total_projection'] - $this->givingTradeSummary['total_projection']) > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ ($this->receivingTradeSummary['total_projection'] - $this->givingTradeSummary['total_projection']) > 0 ? '+' : '' }}{{ number_format($this->receivingTradeSummary['total_projection'] - $this->givingTradeSummary['total_projection'], 1) }} pts
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Risk Analysis -->
                    <div>
                        <flux:heading size="sm" class="mb-4">Risk Analysis</flux:heading>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <span class="font-medium">Giving Risk Score:</span>
                                <span class="text-red-600 font-bold">{{ number_format($this->givingTradeSummary['risk_score'], 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <span class="font-medium">Receiving Risk Score:</span>
                                <span class="text-green-600 font-bold">{{ number_format($this->receivingTradeSummary['risk_score'], 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                <span class="font-medium">Risk Differential:</span>
                                <span class="font-bold {{ ($this->receivingTradeSummary['risk_score'] - $this->givingTradeSummary['risk_score']) < 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ ($this->receivingTradeSummary['risk_score'] - $this->givingTradeSummary['risk_score']) > 0 ? '+' : '' }}{{ number_format($this->receivingTradeSummary['risk_score'] - $this->givingTradeSummary['risk_score'], 2) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trade Insights -->
                <div class="mt-8">
                    <flux:heading size="sm" class="mb-4">Trade Insights</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if ($this->tradeAnalysis['trade_value'] > 2)
                            <div class="p-4 bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <span class="text-green-600 text-xl">✓</span>
                                    <div>
                                        <div class="font-medium text-green-800 dark:text-green-200">Good Trade Value</div>
                                        <div class="text-sm text-green-700 dark:text-green-300">You're receiving {{ number_format(abs($this->tradeAnalysis['trade_value']), 1) }} more value than you're giving up</div>
                                    </div>
                                </div>
                            </div>
                        @elseif ($this->tradeAnalysis['trade_value'] < -2)
                            <div class="p-4 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <span class="text-red-600 text-xl">⚠</span>
                                    <div>
                                        <div class="font-medium text-red-800 dark:text-red-200">Poor Trade Value</div>
                                        <div class="text-sm text-red-700 dark:text-red-300">You're giving up {{ number_format(abs($this->tradeAnalysis['trade_value']), 1) }} more value than you're receiving</div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($this->tradeAnalysis['risk_differential'] < -0.5)
                            <div class="p-4 bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <span class="text-green-600 text-xl">✓</span>
                                    <div>
                                        <div class="font-medium text-green-800 dark:text-green-200">Lower Risk</div>
                                        <div class="text-sm text-green-700 dark:text-green-300">You're receiving lower risk players compared to what you're giving up</div>
                                    </div>
                                </div>
                            </div>
                        @elseif ($this->tradeAnalysis['risk_differential'] > 0.5)
                            <div class="p-4 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <span class="text-red-600 text-xl">⚠</span>
                                    <div>
                                        <div class="font-medium text-red-800 dark:text-red-200">Higher Risk</div>
                                        <div class="text-sm text-red-700 dark:text-red-300">You're receiving higher risk players compared to what you're giving up</div>
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
