<?php

use App\Models\Player;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;

new class extends Component
{
    public $search = '';

    public $position = '';

    public $team = '';

    public $selectedLeagueId = '';

    public $faOnly = false;

    public $sortBy = 'adp';

    public $sortDirection = 'asc';

    public function mount()
    {
        $this->search = request('search', '');
        $this->position = request('position', '');
        $this->team = request('team', '');
        $this->selectedLeagueId = request('league_id', '');
        $this->faOnly = request()->boolean('fa_only');

        // Auto-select first league if none selected
        if (Auth::check() && !$this->selectedLeagueId && !empty($this->leagues)) {
            $this->selectedLeagueId = $this->leagues[0]['id'] ?? '';
        }
    }

    public function sort($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function getPlayersProperty()
    {
        // Build query
        $query = Player::query()
            ->whereIn('position', ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'])
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('first_name', 'like', '%'.$this->search.'%')
                        ->orWhere('last_name', 'like', '%'.$this->search.'%')
                        ->orWhere('full_name', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->position, function ($q) {
                $q->where('position', $this->position);
            })
            ->when($this->team, function ($q) {
                $q->where('team', $this->team);
            })
            ->where('active', true);

        // Filter by free agents only if requested
        if ($this->faOnly && $this->selectedLeagueId) {
            $rosteredPlayerIds = $this->rosteredPlayers->keys()->toArray();
            $query->whereNotIn('player_id', $rosteredPlayerIds);
        }

        // Apply sorting
        if ($this->sortBy) {
            switch ($this->sortBy) {
                case 'name':
                    $query->orderBy('first_name', $this->sortDirection)
                          ->orderBy('last_name', $this->sortDirection);
                    break;
                case 'position':
                    $query->orderBy('position', $this->sortDirection);
                    break;
                case 'team':
                    $query->orderBy('team', $this->sortDirection);
                    break;
                case 'adp':
                    if ($this->sortDirection === 'asc') {
                        $query->orderByRaw('adp IS NULL, adp ASC');
                    } else {
                        $query->orderByRaw('adp IS NOT NULL, adp DESC');
                    }
                    break;
                case 'age':
                    $query->orderByRaw($this->sortDirection === 'asc' ? 'age IS NULL, age ASC' : 'age IS NOT NULL, age DESC');
                    break;
                default:
                    $query->orderByRaw('adp IS NULL, adp ASC');
            }
        } else {
            $query->orderByRaw('adp IS NULL, adp ASC');
        }

        // Get players for current page with relationships
        $players = $query->with(['stats2024', 'projections2025'])
            ->paginate(25);

        // Add player stats and roster information for each player
        foreach ($players as $player) {
            $player->season_2024_summary = $player->getSeason2024Summary();
            $player->season_2025_projections = $player->getSeason2025ProjectionSummary();

            // Add roster information
            $rosterInfo = $this->rosteredPlayers->get($player->player_id);
            $player->owner = $rosterInfo ? $rosterInfo['owner'] : 'Free Agent';
            $player->is_rostered = $rosterInfo !== null;
        }

        return $players;
    }

    public function getStatsProperty()
    {
        $totalPlayers = Player::where('active', true)->count();
        $injuredPlayers = Player::where('active', true)
            ->whereNotNull('injury_status')
            ->where('injury_status', '!=', 'Healthy')
            ->count();

        return [
            'total_players' => $totalPlayers,
            'injured_players' => $injuredPlayers,
            'players_by_position' => [],
            'players_by_team' => [],
        ];
    }

    public function getAvailablePositionsProperty()
    {
        return Cache::remember('player_positions', now()->addHours(1), function () {
            return Player::whereNotNull('position')
                ->where('active', true)
                ->whereIn('position', ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'])
                ->distinct()
                ->pluck('position')
                ->sort()
                ->values()
                ->toArray();
        });
    }

    public function getAvailableTeamsProperty()
    {
        return Cache::remember('player_teams', now()->addHours(1), function () {
            return Player::whereNotNull('team')
                ->where('active', true)
                ->distinct()
                ->pluck('team')
                ->sort()
                ->values()
                ->toArray();
        });
    }

    public function getLeaguesProperty()
    {
        if (!Auth::check()) {
            return [];
        }

        try {
            // Use the MCP tool to fetch user leagues
            $userIdentifier = Auth::user()->sleeper_username ?? Auth::user()->sleeper_user_id;
            if (!$userIdentifier) {
                return [];
            }

            // Use the Sleeper API to fetch user leagues
            $response = \MichaelCrowcroft\SleeperLaravel\Facades\Sleeper::user()->leagues($userIdentifier, 'nfl', date('Y'));

            if ($response->successful()) {
                $leagues = $response->json();
            } else {
                $leagues = [];
            }

            return $leagues ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getRosteredPlayersProperty()
    {
        if (!$this->selectedLeagueId) {
            return collect();
        }

        try {
            // Get all rosters for the selected league
            $response = \MichaelCrowcroft\SleeperLaravel\Facades\Sleeper::league()->rosters($this->selectedLeagueId);
            $rosters = $response->successful() ? $response->json() : [];

            $rosteredPlayers = collect();

            if ($rosters) {
                foreach ($rosters as $roster) {
                    $rosterId = $roster['roster_id'];
                    $ownerName = $roster['owner_name'] ?? 'Unknown Owner';

                    // Add all players from this roster
                    if (isset($roster['players']) && is_array($roster['players'])) {
                        foreach ($roster['players'] as $playerId) {
                            $rosteredPlayers->put($playerId, [
                                'owner' => $ownerName,
                                'roster_id' => $rosterId
                            ]);
                        }
                    }
                }
            }

            return $rosteredPlayers;
        } catch (\Throwable $e) {
            return collect();
        }
    }


    public function getResolvedWeekProperty()
    {
        try {
            $resp = \MichaelCrowcroft\SleeperLaravel\Facades\Sleeper::state()->current('nfl');
            if ($resp->successful()) {
                $state = $resp->json();
                $w = isset($state['week']) ? (int) $state['week'] : null;

                return ($w && $w >= 1 && $w <= 18) ? $w : null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}; ?>

<section class="w-full max-w-none">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">Players</flux:heading>
                <p class="text-muted-foreground mt-1">Browse and filter fantasy football players</p>
            </div>
        </div>

        @if ($this->resolvedWeek)
            <flux:callout>
                NFL Week {{ $this->resolvedWeek }}
            </flux:callout>
        @endif

        <!-- Summary Statistics -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <flux:callout>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ number_format($this->stats['total_players']) }}</div>
                    <div class="text-sm text-muted-foreground">Total Players</div>
                </div>
            </flux:callout>

            <flux:callout>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600">{{ $this->stats['injured_players'] }}</div>
                    <div class="text-sm text-muted-foreground">Injured Players</div>
                </div>
            </flux:callout>

            <flux:callout>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ count($this->stats['players_by_position']) }}</div>
                    <div class="text-sm text-muted-foreground">Positions</div>
                </div>
            </flux:callout>

            <flux:callout>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ count($this->stats['players_by_team']) }}</div>
                    <div class="text-sm text-muted-foreground">Teams</div>
                </div>
            </flux:callout>
        </div>

        <!-- Filters -->
        <flux:callout>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                <!-- Search -->
                <div>
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search players..."
                        type="search"
                    />
                </div>

                <!-- Position Filter -->
                <div>
                    <flux:select wire:model.live="position">
                        <flux:select.option value="">All Positions</flux:select.option>
                        @foreach ($this->availablePositions as $pos)
                            <flux:select.option value="{{ $pos }}">{{ $pos }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Team Filter -->
                <div>
                    <flux:select wire:model.live="team">
                        <flux:select.option value="">All Teams</flux:select.option>
                        @foreach ($this->availableTeams as $teamCode)
                            <flux:select.option value="{{ $teamCode }}">{{ $teamCode }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- League Selection -->
                <div>
                    <flux:select wire:model.live="selectedLeagueId">
                        <flux:select.option value="">No League Selected</flux:select.option>
                        @foreach ($this->leagues as $league)
                            <flux:select.option value="{{ $league['id'] }}">{{ $league['name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Free Agents Only -->
                @if($selectedLeagueId)
                    <div>
                        <label class="flex items-center gap-2 text-sm">
                            <flux:switch wire:model.live="faOnly" />
                            <span>Free agents only</span>
                        </label>
                    </div>
                @endif

                <!-- Filter Button -->
                <div>
                    <flux:button variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove>Filter</span>
                        <span wire:loading>Filtering...</span>
                    </flux:button>
                </div>
            </div>
        </flux:callout>

        <!-- Players Table -->
        <div class="overflow-x-auto">
            <flux:table :paginate="$this->players" class="min-w-full">
                <flux:table.columns>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'name'"
                        :direction="$sortDirection"
                        wire:click="sort('name')"
                        class="cursor-pointer"
                    >
                        Player
                    </flux:table.column>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'position'"
                        :direction="$sortDirection"
                        wire:click="sort('position')"
                        class="cursor-pointer"
                    >
                        Position
                    </flux:table.column>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'team'"
                        :direction="$sortDirection"
                        wire:click="sort('team')"
                        class="cursor-pointer"
                    >
                        Team
                    </flux:table.column>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'age'"
                        :direction="$sortDirection"
                        wire:click="sort('age')"
                        class="cursor-pointer"
                    >
                        Age
                    </flux:table.column>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'adp'"
                        :direction="$sortDirection"
                        wire:click="sort('adp')"
                        class="cursor-pointer"
                    >
                        ADP
                    </flux:table.column>
                    <flux:table.column>2024 PPG</flux:table.column>
                    <flux:table.column>2024 Total</flux:table.column>
                    <flux:table.column>2025 Proj PPG</flux:table.column>
                    <flux:table.column>2025 Total</flux:table.column>
                    @if($this->resolvedWeek && $this->resolvedWeek <= 18)
                        <flux:table.column>2024 Last 4</flux:table.column>
                    @endif
                    <flux:table.column>Owner</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->players as $player)
                        <flux:table.row key="{{ $player->player_id }}" wire:key="player-{{ $player->player_id }}">
                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ $player->first_name }} {{ $player->last_name }}</span>
                                    @if ($player->height || $player->weight)
                                        <span class="text-xs text-muted-foreground">
                                            @if($player->height)
                                                {{ $player->height }}
                                            @endif
                                            @if($player->height && $player->weight)
                                                ,
                                            @endif
                                            @if($player->weight)
                                                {{ $player->weight }} lbs
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge variant="secondary" color="green">{{ $player->position }}</flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge variant="outline">{{ $player->team }}</flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if ($player->age)
                                    {{ $player->age }}
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @if ($player->adp_formatted)
                                    <span class="font-medium">{{ $player->adp_formatted }}</span>
                                @elseif ($player->adp)
                                    {{ number_format($player->adp, 1) }}
                                @else
                                    <span class="text-muted-foreground">Undrafted</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @if (isset($player->season_2024_summary) && isset($player->season_2024_summary['average_points_per_game']) && $player->season_2024_summary['average_points_per_game'] > 0)
                                    <span class="font-medium text-green-600">{{ number_format($player->season_2024_summary['average_points_per_game'], 1) }}</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @if (isset($player->season_2024_summary) && isset($player->season_2024_summary['total_points']) && $player->season_2024_summary['total_points'] > 0)
                                    {{ number_format($player->season_2024_summary['total_points'], 1) }}
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @if (isset($player->season_2025_projections) && isset($player->season_2025_projections['average_points_per_game']) && $player->season_2025_projections['average_points_per_game'] > 0)
                                    <span class="font-medium text-blue-600">{{ number_format($player->season_2025_projections['average_points_per_game'], 1) }}</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @if (isset($player->season_2025_projections) && isset($player->season_2025_projections['total_points']) && $player->season_2025_projections['total_points'] > 0)
                                    {{ number_format($player->season_2025_projections['total_points'], 1) }}
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>

                            @if($this->resolvedWeek && $this->resolvedWeek <= 18)
                                <flux:table.cell>
                                    @if (isset($player->season_2024_summary) && isset($player->season_2024_summary['last_4_games_avg']) && $player->season_2024_summary['last_4_games_avg'] > 0)
                                        <span class="font-medium">{{ number_format($player->season_2024_summary['last_4_games_avg'], 1) }}</span>
                                    @else
                                        <span class="text-muted-foreground">-</span>
                                    @endif
                                </flux:table.cell>
                            @endif

                            <flux:table.cell>
                                @if ($player->is_rostered)
                                    <flux:badge variant="secondary" color="blue">{{ $player->owner }}</flux:badge>
                                @else
                                    <flux:badge variant="outline" color="gray">Free Agent</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @if ($player->injury_status && $player->injury_status !== 'Healthy')
                                    <flux:badge color="red" size="sm">{{ $player->injury_status }}</flux:badge>
                                @else
                                    <flux:badge color="green" size="sm">Healthy</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    href="{{ route('players.show', $player->player_id) }}"
                                >
                                    View Details
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="{{ ($this->resolvedWeek && $this->resolvedWeek <= 18) ? 13 : 12 }}" align="center">
                                <div class="py-8 text-muted-foreground">
                                    No players found matching your filters.
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <!-- Results Summary -->
        <div class="text-sm text-muted-foreground">
            Showing {{ $this->players->count() }} of {{ $this->players->total() }} players
        </div>
    </div>
</section>