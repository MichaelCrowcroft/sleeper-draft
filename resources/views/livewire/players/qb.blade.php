<?php

use App\Models\Player;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

new class extends Component
{
    public $search = '';

    public $position = 'QB';

    public $team = '';

    public $selectedLeagueId = '';

    public $faOnly = false;

    public $sortBy = 'adp';

    public $sortDirection = 'asc';

    public $selectedMetrics = [
        'age' => true,
        'adp' => true,
        'avg_ppg_2024' => true,
        'stddev_above' => false,
        'stddev_below' => false,
        'proj_ppg_2025' => true,
        'owner' => true,
        'status' => true,
        // QB specific metrics (default on)
        'pass_att' => true,
        'pass_cmp' => true,
        'pass_yd' => true,
        'pass_td' => true,
        'pass_int' => true,
        'pass_sack' => true,
        'cmp_pct' => true,
        'rush_att' => true,
        'rush_yd' => true,
        'rush_td' => true,
        'proj_pts_week' => true,
        // Additional QB metrics (default off)
        'pass_inc' => false,
        'pass_fd' => false,
        'pass_cmp_40p' => false,
        'pass_2pt' => false,
        'pass_int_td' => false,
        'rush_fd' => false,
        'rush_40p' => false,
    ];

    public function mount()
    {
        $this->search = request('search', '');
        $this->team = request('team', '');
        $this->selectedLeagueId = request('league_id', '');
        $this->faOnly = request()->boolean('fa_only');

        // Auto-select first league if none selected and user is authenticated
        if (Auth::check() && !$this->selectedLeagueId) {
            $leagues = $this->getLeaguesProperty();
            if (!empty($leagues)) {
                $this->selectedLeagueId = $leagues[0]['league_id'] ?? '';
            }
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
            ->where('position', 'QB')
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('first_name', 'like', '%'.$this->search.'%')
                        ->orWhere('last_name', 'like', '%'.$this->search.'%')
                        ->orWhere('full_name', 'like', '%'.$this->search.'%');
                });
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

            // Compute projected points for the current week (PPR)
            $player->proj_pts_week = null;
            if ($this->resolvedWeek) {
                // Prefer using the eager-loaded projection for this week if present
                $weekly = null;
                if ($player->relationLoaded('projections2025')) {
                    $weekly = optional($player->projections2025)->firstWhere('week', $this->resolvedWeek);
                }
                if ($weekly) {
                    $stats = is_array($weekly->stats ?? null) ? $weekly->stats : null;
                    if ($stats && isset($stats['pts_ppr']) && is_numeric($stats['pts_ppr'])) {
                        $player->proj_pts_week = (float) $stats['pts_ppr'];
                    } elseif (isset($weekly->pts_ppr) && is_numeric($weekly->pts_ppr)) {
                        $player->proj_pts_week = (float) $weekly->pts_ppr;
                    }
                } else {
                    $player->proj_pts_week = $player->getProjectedPointsForWeek(2025, (int) $this->resolvedWeek);
                }
            }

            // Compute per-game averages for all 2025 projection metrics
            $player->season_2025_avg_metrics = $player->getSeason2025ProjectionsAverages();

            // Add roster information
            $rosterInfo = $this->rosteredPlayers->get($player->player_id);
            $player->owner = $rosterInfo ? $rosterInfo['owner'] : 'Free Agent';
            $player->is_rostered = $rosterInfo !== null;
        }

        return $players;
    }

    public function getStatsProperty()
    {
        $totalPlayers = Player::where('active', true)->where('position', 'QB')->count();
        $injuredPlayers = Player::where('active', true)
            ->where('position', 'QB')
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

    public function getAvailableTeamsProperty()
    {
        return Cache::remember('qb_teams', now()->addHours(1), function () {
            return Player::whereNotNull('team')
                ->where('active', true)
                ->where('position', 'QB')
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
            // Use the sleeper_user_id (required for API calls)
            $userIdentifier = Auth::user()->sleeper_user_id;
            if (!$userIdentifier) {
                return [];
            }

            // Use the Sleeper API to fetch user leagues
            $response = \MichaelCrowcroft\SleeperLaravel\Facades\Sleeper::user($userIdentifier)->leagues('nfl', date('Y'));

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

        // Get all rosters for the selected league
        $rostersResponse = Sleeper::league($this->selectedLeagueId)->rosters();
        $rosters = $rostersResponse->successful() ? $rostersResponse->json() : [];

        // Get all users for the selected league to map owner IDs to names
        $usersResponse = Sleeper::league($this->selectedLeagueId)->users();
        $users = $usersResponse->successful() ? $usersResponse->json() : [];

        // Create a mapping of user_id to display_name
        $userMap = collect($users)->keyBy('user_id')->map(function ($user) {
            return $user['display_name'] ?? $user['username'] ?? 'Unknown Owner';
        });

        $rosteredPlayers = collect();

        if ($rosters) {
            foreach ($rosters as $roster) {
                $rosterId = $roster['roster_id'];
                $ownerId = $roster['owner_id'] ?? null;
                $ownerName = $ownerId ? $userMap->get($ownerId, 'Unknown Owner') : 'Unknown Owner';

                // Add all players from this roster
                if (isset($roster['players']) && is_array($roster['players'])) {
                    foreach ($roster['players'] as $playerId) {
                        $rosteredPlayers->put($playerId, [
                            'owner' => $ownerName,
                            'roster_id' => $rosterId,
                            'owner_id' => $ownerId
                        ]);
                    }
                }
            }
        }

        return $rosteredPlayers;
    }

    public function getColspan()
    {
        // Base columns that are always shown: Player, Pos, Team, Actions
        $colspan = 4;

        // Add conditional columns
        foreach ($this->selectedMetrics as $key => $enabled) {
            if ($enabled === true) {
                $colspan++;
            }
        }

        return $colspan;
    }

    public function getResolvedWeekProperty()
    {
            $resp = Sleeper::state()->current('nfl');
            $state = $resp->json();
            $w = isset($state['week']) ? (int) $state['week'] : null;

            return ($w && $w >= 1 && $w <= 18) ? $w : null;
    }

    public function getTrendingPicksProperty()
    {
        // Mock data for now - in a real implementation, you'd fetch from an API
        return collect([
            ['first_name' => 'Lamar', 'last_name' => 'Jackson', 'trend_count' => 25],
            ['first_name' => 'Patrick', 'last_name' => 'Mahomes', 'trend_count' => 18],
            ['first_name' => 'Josh', 'last_name' => 'Allen', 'trend_count' => 15],
        ]);
    }

    public function getTrendingDropsProperty()
    {
        // Mock data for now - in a real implementation, you'd fetch from an API
        return collect([
            ['first_name' => 'Justin', 'last_name' => 'Fields', 'trend_count' => 12],
            ['first_name' => 'Deshaun', 'last_name' => 'Watson', 'trend_count' => 8],
            ['first_name' => 'Zach', 'last_name' => 'Wilson', 'trend_count' => 6],
        ]);
    }
}; ?>

<section class="w-full max-w-none">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">Quarterbacks</flux:heading>
                <p class="text-muted-foreground mt-1">Browse and analyze QB prospects with passing and rushing metrics</p>
            </div>
        </div>

        @if ($this->resolvedWeek)
            <flux:callout>
                NFL Week {{ $this->resolvedWeek }}
            </flux:callout>
        @endif

        <!-- Trending Section -->
        <div class="grid gap-4 md:grid-cols-2">
            <flux:callout>
                <div class="space-y-4">
                    <flux:heading size="md" class="text-green-600">🔥 Trending Up</flux:heading>
                    <div class="space-y-2">
                        @forelse ($this->trendingPicks as $player)
                            <div class="flex justify-between items-center p-2 bg-green-50 rounded">
                                <span class="font-medium">{{ $player['first_name'] }} {{ $player['last_name'] }}</span>
                                <flux:badge variant="secondary" color="green">+{{ $player['trend_count'] ?? 0 }}</flux:badge>
                            </div>
                        @empty
                            <p class="text-muted-foreground">No trending QB picks available</p>
                        @endforelse
                    </div>
                </div>
            </flux:callout>

            <flux:callout>
                <div class="space-y-4">
                    <flux:heading size="md" class="text-red-600">📉 Trending Down</flux:heading>
                    <div class="space-y-2">
                        @forelse ($this->trendingDrops as $player)
                            <div class="flex justify-between items-center p-2 bg-red-50 rounded">
                                <span class="font-medium">{{ $player['first_name'] }} {{ $player['last_name'] }}</span>
                                <flux:badge variant="secondary" color="red">-{{ $player['trend_count'] ?? 0 }}</flux:badge>
                            </div>
                        @empty
                            <p class="text-muted-foreground">No trending QB drops available</p>
                        @endforelse
                    </div>
                </div>
            </flux:callout>
        </div>

        <!-- Summary Statistics -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <flux:callout>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ number_format($this->stats['total_players']) }}</div>
                    <div class="text-sm text-muted-foreground">Total QBs</div>
                </div>
            </flux:callout>

            <flux:callout>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600">{{ $this->stats['injured_players'] }}</div>
                    <div class="text-sm text-muted-foreground">Injured QBs</div>
                </div>
            </flux:callout>

            <flux:callout>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ count($this->availableTeams) }}</div>
                    <div class="text-sm text-muted-foreground">Teams</div>
                </div>
            </flux:callout>

            <flux:callout>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">{{ $this->players->total() }}</div>
                    <div class="text-sm text-muted-foreground">Filtered QBs</div>
                </div>
            </flux:callout>
        </div>

        <!-- Filters -->
        <flux:callout>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5 place-items-center">
                <!-- Search -->
                <div>
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search QBs..."
                        type="search"
                    />
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
                            <flux:select.option value="{{ $league['league_id'] }}">{{ $league['name'] }}</flux:select.option>
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

        <!-- Metric Selector -->
        <flux:callout>
            <div class="space-y-4">
                <flux:heading size="sm">QB Metrics</flux:heading>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <label class="flex items-center gap-2 text-sm">
                        <flux:switch wire:model.live="selectedMetrics.age" />
                        <span>Age</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <flux:switch wire:model.live="selectedMetrics.adp" />
                        <span>ADP</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <flux:switch wire:model.live="selectedMetrics.avg_ppg_2024" />
                        <span>Avg PPG (2024)</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <flux:switch wire:model.live="selectedMetrics.proj_ppg_2025" />
                        <span>Proj PPG (2025)</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <flux:switch wire:model.live="selectedMetrics.proj_pts_week" />
                        <span>Projected Points (This Week)</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <flux:switch wire:model.live="selectedMetrics.owner" />
                        <span>Owner</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <flux:switch wire:model.live="selectedMetrics.status" />
                        <span>Status</span>
                    </label>
                </div>

                <div class="mt-4">
                    <flux:heading size="xs">Passing & Rushing Stats (per game)</flux:heading>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mt-2">
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_att" /><span>Pass Attempts</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_cmp" /><span>Pass Completions</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_yd" /><span>Passing Yards</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_td" /><span>Passing TDs</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_int" /><span>Interceptions</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_sack" /><span>Sacks Taken</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.cmp_pct" /><span>Completion %</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rush_att" /><span>Rush Attempts</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rush_yd" /><span>Rushing Yards</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rush_td" /><span>Rushing TDs</span></label>
                    </div>
                </div>
            </div>
        </flux:callout>

        <!-- Players Table -->
        <flux:callout class="overflow-x-auto">
            <flux:heading size="md" class="mb-4">Quarterback Data</flux:heading>
            <div class="w-full max-w-full">
                <flux:table :paginate="$this->players" class="min-w-max">
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
                    <flux:table.column>Pos</flux:table.column>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'team'"
                        :direction="$sortDirection"
                        wire:click="sort('team')"
                        class="cursor-pointer"
                    >
                        Team
                    </flux:table.column>
                    @if($selectedMetrics['age'])
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'age'"
                        :direction="$sortDirection"
                        wire:click="sort('age')"
                        class="cursor-pointer"
                    >
                        Age
                    </flux:table.column>
                    @endif
                    @if($selectedMetrics['adp'])
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'adp'"
                        :direction="$sortDirection"
                        wire:click="sort('adp')"
                        class="cursor-pointer"
                    >
                        ADP
                    </flux:table.column>
                    @endif
                    @if($selectedMetrics['avg_ppg_2024'])
                    <flux:table.column>Avg PPG (2024)</flux:table.column>
                    @endif
                    @if($selectedMetrics['proj_ppg_2025'])
                    <flux:table.column>Proj PPG (2025)</flux:table.column>
                    @endif
                    @if($selectedMetrics['proj_pts_week'])
                    <flux:table.column>Proj Pts (This Week)</flux:table.column>
                    @endif
                    @if($selectedMetrics['owner'])
                    <flux:table.column>Owner</flux:table.column>
                    @endif
                    @if($selectedMetrics['status'])
                    <flux:table.column>Status</flux:table.column>
                    @endif
                    @if($selectedMetrics['pass_att'])<flux:table.column>Pass Att</flux:table.column>@endif
                    @if($selectedMetrics['pass_cmp'])<flux:table.column>Pass Cmp</flux:table.column>@endif
                    @if($selectedMetrics['pass_yd'])<flux:table.column>Pass Yds</flux:table.column>@endif
                    @if($selectedMetrics['pass_td'])<flux:table.column>Pass TD</flux:table.column>@endif
                    @if($selectedMetrics['pass_int'])<flux:table.column>INT</flux:table.column>@endif
                    @if($selectedMetrics['pass_sack'])<flux:table.column>Sack</flux:table.column>@endif
                    @if($selectedMetrics['cmp_pct'])<flux:table.column>Cmp %</flux:table.column>@endif
                    @if($selectedMetrics['rush_att'])<flux:table.column>Rush Att</flux:table.column>@endif
                    @if($selectedMetrics['rush_yd'])<flux:table.column>Rush Yds</flux:table.column>@endif
                    @if($selectedMetrics['rush_td'])<flux:table.column>Rush TD</flux:table.column>@endif
                    <flux:table.column>Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($this->players as $player)
                        <flux:table.row key="{{ $player->player_id }}" wire:key="player-{{ $player->player_id }}">
                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ $player->first_name }} {{ $player->last_name }}</span>
                                    @if($player->height || $player->weight)
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

                            @if($selectedMetrics['age'])
                            <flux:table.cell>
                                @if ($player->age)
                                    {{ $player->age }}
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['adp'])
                            <flux:table.cell>
                                @if ($player->adp_formatted)
                                    <span class="font-medium">{{ $player->adp_formatted }}</span>
                                @elseif ($player->adp)
                                    {{ number_format($player->adp, 1) }}
                                @else
                                    <span class="text-muted-foreground">Undrafted</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['avg_ppg_2024'])
                            <flux:table.cell>
                                @if (isset($player->season_2024_summary) && isset($player->season_2024_summary['average_points_per_game']) && $player->season_2024_summary['average_points_per_game'] > 0)
                                    <span class="font-medium text-green-600">{{ number_format($player->season_2024_summary['average_points_per_game'], 1) }}</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['proj_ppg_2025'])
                            <flux:table.cell>
                                @if (isset($player->season_2025_projections) && isset($player->season_2025_projections['average_points_per_game']) && $player->season_2025_projections['average_points_per_game'] > 0)
                                    <span class="font-medium text-blue-600">{{ number_format($player->season_2025_projections['average_points_per_game'], 1) }}</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['proj_pts_week'])
                            <flux:table.cell>
                                @if (!is_null($player->proj_pts_week))
                                    <span class="font-medium text-blue-700">{{ number_format($player->proj_pts_week, 1) }}</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['owner'])
                            <flux:table.cell>
                                @if ($player->is_rostered)
                                    <flux:badge variant="secondary" color="blue">{{ $player->owner }}</flux:badge>
                                @else
                                    <flux:badge variant="outline" color="gray">Free Agent</flux:badge>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['status'])
                            <flux:table.cell>
                                @if ($player->injury_status && $player->injury_status !== 'Healthy')
                                    <flux:badge color="red" size="sm">{{ $player->injury_status }}</flux:badge>
                                @else
                                    <flux:badge color="green" size="sm">Healthy</flux:badge>
                                @endif
                            </flux:table.cell>
                            @endif

                            @php
                                $avg = $player->season_2025_avg_metrics ?? [];
                                $fmt = fn($v) => number_format((float) $v, 1);
                                $val = fn($key) => isset($avg[$key]) && is_numeric($avg[$key]) ? $fmt($avg[$key]) : null;
                                $passCmp = $avg['pass_cmp'] ?? null;
                                $passAtt = $avg['pass_att'] ?? null;
                                $cmpPctComputed = (is_numeric($passCmp) && is_numeric($passAtt) && $passAtt > 0) ? (100.0 * ((float)$passCmp) / ((float)$passAtt)) : null;
                                $cmpPct = isset($avg['cmp_pct']) && is_numeric($avg['cmp_pct']) ? (float)$avg['cmp_pct'] : $cmpPctComputed;
                            @endphp

                            @if($selectedMetrics['pass_att'])<flux:table.cell>{{ $val('pass_att') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_cmp'])<flux:table.cell>{{ $val('pass_cmp') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_yd'])<flux:table.cell>{{ $val('pass_yd') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_td'])<flux:table.cell>{{ $val('pass_td') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_int'])<flux:table.cell>{{ $val('pass_int') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_sack'])<flux:table.cell>{{ $val('pass_sack') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['cmp_pct'])<flux:table.cell>{{ !is_null($cmpPct) ? number_format($cmpPct, 1).'%' : '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rush_att'])<flux:table.cell>{{ $val('rush_att') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rush_yd'])<flux:table.cell>{{ $val('rush_yd') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rush_td'])<flux:table.cell>{{ $val('rush_td') ?? '-' }}</flux:table.cell>@endif

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
                            <flux:table.cell :colspan="$this->getColspan()" align="center">
                                <div class="py-8 text-muted-foreground">
                                    No quarterbacks found matching your filters.
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
        </flux:callout>
    </div>
</section>
