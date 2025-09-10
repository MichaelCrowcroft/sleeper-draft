<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use App\Actions\Matchups\DetermineCurrentWeek;
use App\Actions\Sleeper\FetchUserLeagues;
use App\Actions\Players\FetchTrending;
use App\Actions\Players\FetchGlobalStats;
use App\Actions\Players\FetchAvailablePositions;
use App\Actions\Players\FetchAvailableTeams;
use App\Actions\Players\ApplyPositionPreset;
use App\Actions\Players\BuildPlayersTable;

new class extends Component
{
    public $search = '';

    public $position = '';

    public $team = '';

    public $selectedLeagueId = '';

    public $faOnly = false;

    public $sortBy = 'adp';

    public $sortDirection = 'asc';

    public $selectedMetrics = [
        'age' => true,
        'adp' => true,
        'avg_ppg_2024' => true,
        'position_rank_2024' => true,
        'snap_pct_2024' => true,
        'target_share_2024' => true,
        'stddev_above' => true,
        'stddev_below' => true,
        'proj_ppg_2025' => true,
        'owner' => true,
        'status' => true,
        // Additional metrics (default off)
        'proj_pts_week' => false,
        'weekly_position_rank' => false,
        // 2025 projection averages (per-game)
        'rec' => false,
        'rec_0_4' => false,
        'rec_5_9' => false,
        'rec_10_19' => false,
        'rec_20_29' => false,
        'rec_30_39' => false,
        'rec_40p' => false,
        'rec_2pt' => false,
        'rec_fd' => false,
        'rec_td' => false,
        'rec_tgt' => false,
        'rec_yd' => false,
        'rush_40p' => false,
        'rush_att' => false,
        'rush_fd' => false,
        'rush_td' => false,
        'rush_yd' => false,
        'pass_2pt' => false,
        'pass_att' => false,
        'pass_cmp' => false,
        'pass_cmp_40p' => false,
        'pass_fd' => false,
        'pass_inc' => false,
        'pass_int' => false,
        'pass_int_td' => false,
        'pass_sack' => false,
        'pass_td' => false,
        'pass_yd' => false,
        'cmp_pct' => false,
        'def_fum_td' => false,
        'fum' => false,
        'fum_lost' => false,
    ];

    public function mount(ApplyPositionPreset $positionPreset)
    {
        $this->search = request('search', '');
        // Allow position from query or route param or route name
        $this->position = request('position', request()->route('position', ''));
        $this->team = request('team', '');
        $this->selectedLeagueId = request('league_id', '');
        $this->faOnly = request()->boolean('fa_only');

        if($this->position === '') {
            $routeName = optional(request()->route())->getName();
            $map = [
                'players.qb' => 'QB',
                'players.rb' => 'RB',
                'players.wr' => 'WR',
                'players.te' => 'TE',
                'players.k' => 'K',
                'players.def' => 'DEF',
            ];
            if ($routeName && isset($map[$routeName])) {
                $this->position = $map[$routeName];
            }
        }

        $this->selectedMetrics = $positionPreset->execute($this->position, $this->selectedMetrics);

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

    public function getIsReceivingPositionProperty(): bool
    {
        $pos = strtoupper((string) $this->position);

        return in_array($pos, ['WR', 'TE', 'RB'], true);
    }

    public function getIsRushingPositionProperty(): bool
    {
        $pos = strtoupper((string) $this->position);

        return in_array($pos, ['RB', 'QB'], true);
    }

    public function getIsPassingPositionProperty(): bool
    {
        return strtoupper((string) $this->position) === 'QB';
    }

    public function getIsDefensePositionProperty(): bool
    {
        return strtoupper((string) $this->position) === 'DEF';
    }

    public function getPlayersProperty()
    {
        return app(BuildPlayersTable::class)->execute([
            'search' => $this->search,
            'position' => $this->position,
            'team' => $this->team,
            'sortBy' => $this->sortBy ?: 'adp',
            'sortDirection' => $this->sortDirection ?: 'asc',
            'league_id' => $this->selectedLeagueId ?: null,
            'fa_only' => (bool) $this->faOnly,
            'per_page' => 25,
        ]);
    }

    public function getTrendingPicksProperty()
    {
        return app(FetchTrending::class)->execute('add', $this->position, 5);
    }

    public function getTrendingDropsProperty()
    {
        return app(FetchTrending::class)->execute('drop', $this->position, 5);
    }

    public function getStatsProperty()
    {
        return app(FetchGlobalStats::class)->execute();
    }

    public function getAvailablePositionsProperty()
    {
        return app(FetchAvailablePositions::class)->execute();
    }

    public function getAvailableTeamsProperty()
    {
        return app(FetchAvailableTeams::class)->execute();
    }

    public function getLeaguesProperty()
    {
        if (!Auth::check()) {
            return [];
        }

        try {
            $userIdentifier = Auth::user()->sleeper_user_id;
            if (!$userIdentifier) {
                return [];
            }

            return app(FetchUserLeagues::class)->execute($userIdentifier, 'nfl', (int) date('Y')) ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // roster ownership mapping handled by BuildPlayersTable action

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
            $state = app(DetermineCurrentWeek::class)->execute('nfl');
            $w = isset($state['week']) ? (int) $state['week'] : null;

            return ($w && $w >= 1 && $w <= 18) ? $w : null;
    }
}; ?>

<section class="w-full max-w-none">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                @php
                    $titleMap = ['QB' => 'Quarterbacks','RB' => 'Running Backs','WR' => 'Wide Receivers','TE' => 'Tight Ends','K' => 'Kickers','DEF' => 'Defenses'];
                    $pageTitle = $titleMap[$this->position] ?? ($this->position ? ($this->position.' Players') : 'Players');
                @endphp
                <flux:heading size="lg">{{ $pageTitle }}</flux:heading>
                <p class="text-muted-foreground mt-1">Browse and filter fantasy football players</p>
            </div>
            <div class="hidden md:flex items-center gap-2">
                @foreach($this->availablePositions as $pos)
                    @php $posUpper = strtoupper((string) $pos); @endphp
                    <flux:button
                        size="xs"
                        variant="{{ strtoupper((string)$this->position) === $posUpper ? 'primary' : 'outline' }}"
                        href="{{ route('players.byPosition', ['position' => $posUpper]) }}"
                        wire:navigate
                    >{{ $posUpper }}</flux:button>
                @endforeach
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
                            <p class="text-muted-foreground">No trending picks available</p>
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
                            <p class="text-muted-foreground">No trending drops available</p>
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

        <!-- Player Filters -->
        <flux:callout>
            <div class="space-y-4">
                <flux:heading size="sm">Player Filters</flux:heading>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 place-items-center">
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

                    <!-- Filter Button -->
                    <div>
                        <flux:button variant="primary" wire:loading.attr="disabled">
                            <span wire:loading.remove>Filter</span>
                            <span wire:loading>Filtering...</span>
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:callout>

        <!-- League Filters -->
        <flux:callout>
            <div class="space-y-4">
                <flux:heading size="sm">League Filters</flux:heading>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 place-items-center">
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
                    @else
                        <div>
                            <label class="flex items-center gap-2 text-sm text-muted-foreground">
                                <flux:switch disabled />
                                <span>Select a league to filter free agents</span>
                            </label>
                        </div>
                    @endif

                    <!-- Spacer for alignment -->
                    <div></div>
                </div>
            </div>
        </flux:callout>

        <!-- Metric Selector -->
        <flux:callout>
            <div class="space-y-4">
                <flux:heading size="sm">Display Metrics</flux:heading>
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
                            <flux:switch wire:model.live="selectedMetrics.position_rank_2024" />
                            <span>Position Rank (2024)</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <flux:switch wire:model.live="selectedMetrics.snap_pct_2024" />
                            <span>Avg Snap % (2024)</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <flux:switch wire:model.live="selectedMetrics.target_share_2024" />
                            <span>Avg Target % (2024)</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <flux:switch wire:model.live="selectedMetrics.stddev_above" />
                            <span>+1σ PPG (2024)</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <flux:switch wire:model.live="selectedMetrics.stddev_below" />
                            <span>-1σ PPG (2024)</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <flux:switch wire:model.live="selectedMetrics.proj_ppg_2025" />
                            <span>Proj PPG (2025)</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <flux:switch wire:model.live="selectedMetrics.owner" />
                            <span>Owner</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <flux:switch wire:model.live="selectedMetrics.status" />
                            <span>Status</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                        <flux:switch wire:model.live="selectedMetrics.proj_pts_week" />
                        <span>Projected Points (This Week)</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                        <flux:switch wire:model.live="selectedMetrics.weekly_position_rank" />
                        <span>Weekly Position Rank</span>
                        </label>
                </div>

                <div class="mt-4">
                    <flux:heading size="xs">2025 Projection Averages (per game)</flux:heading>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mt-2">
                        @if($this->isReceivingPosition)
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rec" /><span>Receptions</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rec_tgt" /><span>Targets</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rec_yd" /><span>Receiving Yards</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rec_td" /><span>Receiving TDs</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rec_fd" /><span>Receiving First Downs</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rec_2pt" /><span>Receiving 2-pt Conversions</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rec_0_4" /><span>Rec. 0–4 Yds</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rec_5_9" /><span>Rec. 5–9 Yds</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rec_10_19" /><span>Rec. 10–19 Yds</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rec_20_29" /><span>Rec. 20–29 Yds</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rec_30_39" /><span>Rec. 30–39 Yds</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rec_40p" /><span>Rec. 40+ Yds</span></label>
                        @endif

                        @if($this->isRushingPosition)
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rush_att" /><span>Rush Attempts</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rush_yd" /><span>Rushing Yards</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rush_td" /><span>Rushing TDs</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rush_fd" /><span>Rushing First Downs</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rush_40p" /><span>Rush 40+ Yds</span></label>
                        @endif

                        @if($this->isPassingPosition)
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_att" /><span>Pass Attempts</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_cmp" /><span>Pass Completions</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_yd" /><span>Passing Yards</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_td" /><span>Passing TDs</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_int" /><span>Interceptions</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_inc" /><span>Incompletions</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_sack" /><span>Sacks Taken</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_fd" /><span>Passing First Downs</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_cmp_40p" /><span>40+ Yard Completions</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_2pt" /><span>Passing 2-pt Conversions</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.pass_int_td" /><span>Pick Sixes</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.cmp_pct" /><span>Completion %</span></label>
                        @endif

                        @if($this->isDefensePosition)
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.def_fum_td" /><span>Defense Fumble TDs</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.fum" /><span>Fumbles</span></label>
                            <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.fum_lost" /><span>Fumbles Lost</span></label>
                        @endif
                    </div>
                </div>
            </div>
        </flux:callout>

        <!-- Players Table -->
        <flux:callout class="overflow-x-auto">
            <flux:heading size="md" class="mb-4">Player Data</flux:heading>
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
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'position'"
                        :direction="$sortDirection"
                        wire:click="sort('position')"
                        class="cursor-pointer"
                    >
                        Pos
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
                    @if($selectedMetrics['position_rank_2024'])
                    <flux:table.column>Pos Rank (2024)</flux:table.column>
                    @endif
                    @if($selectedMetrics['snap_pct_2024'])
                    <flux:table.column>Avg Snap % (2024)</flux:table.column>
                    @endif
                    @if($selectedMetrics['target_share_2024'])
                    <flux:table.column>Avg Target % (2024)</flux:table.column>
                    @endif
                    @if($selectedMetrics['stddev_above'])
                    <flux:table.column>+1σ PPG (2024)</flux:table.column>
                    @endif
                    @if($selectedMetrics['stddev_below'])
                    <flux:table.column>-1σ PPG (2024)</flux:table.column>
                    @endif
                    @if($selectedMetrics['proj_ppg_2025'])
                    <flux:table.column>Proj PPG (2025)</flux:table.column>
                    @endif
                    @if($selectedMetrics['proj_pts_week'])
                    <flux:table.column>Proj Pts (This Week)</flux:table.column>
                    @endif
                    @if($selectedMetrics['weekly_position_rank'])
                    <flux:table.column>Weekly Pos Rank</flux:table.column>
                    @endif
                    @if($selectedMetrics['owner'])
                    <flux:table.column>Owner</flux:table.column>
                    @endif
                    @if($selectedMetrics['status'])
                    <flux:table.column>Status</flux:table.column>
                    @endif
                    @if($selectedMetrics['rec'])<flux:table.column>Rec</flux:table.column>@endif
                    @if($selectedMetrics['rec_tgt'])<flux:table.column>Tgt</flux:table.column>@endif
                    @if($selectedMetrics['rec_yd'])<flux:table.column>Rec Yds</flux:table.column>@endif
                    @if($selectedMetrics['rec_td'])<flux:table.column>Rec TD</flux:table.column>@endif
                    @if($selectedMetrics['rec_fd'])<flux:table.column>Rec 1D</flux:table.column>@endif
                    @if($selectedMetrics['rec_2pt'])<flux:table.column>Rec 2pt</flux:table.column>@endif
                    @if($selectedMetrics['rec_0_4'])<flux:table.column>Rec 0–4</flux:table.column>@endif
                    @if($selectedMetrics['rec_5_9'])<flux:table.column>Rec 5–9</flux:table.column>@endif
                    @if($selectedMetrics['rec_10_19'])<flux:table.column>Rec 10–19</flux:table.column>@endif
                    @if($selectedMetrics['rec_20_29'])<flux:table.column>Rec 20–29</flux:table.column>@endif
                    @if($selectedMetrics['rec_30_39'])<flux:table.column>Rec 30–39</flux:table.column>@endif
                    @if($selectedMetrics['rec_40p'])<flux:table.column>Rec 40+</flux:table.column>@endif

                    @if($selectedMetrics['rush_att'])<flux:table.column>Rush Att</flux:table.column>@endif
                    @if($selectedMetrics['rush_yd'])<flux:table.column>Rush Yds</flux:table.column>@endif
                    @if($selectedMetrics['rush_td'])<flux:table.column>Rush TD</flux:table.column>@endif
                    @if($selectedMetrics['rush_fd'])<flux:table.column>Rush 1D</flux:table.column>@endif
                    @if($selectedMetrics['rush_40p'])<flux:table.column>Rush 40+</flux:table.column>@endif

                    @if($selectedMetrics['pass_att'])<flux:table.column>Pass Att</flux:table.column>@endif
                    @if($selectedMetrics['pass_cmp'])<flux:table.column>Pass Cmp</flux:table.column>@endif
                    @if($selectedMetrics['pass_yd'])<flux:table.column>Pass Yds</flux:table.column>@endif
                    @if($selectedMetrics['pass_td'])<flux:table.column>Pass TD</flux:table.column>@endif
                    @if($selectedMetrics['pass_int'])<flux:table.column>INT</flux:table.column>@endif
                    @if($selectedMetrics['pass_inc'])<flux:table.column>INC</flux:table.column>@endif
                    @if($selectedMetrics['pass_sack'])<flux:table.column>Sack</flux:table.column>@endif
                    @if($selectedMetrics['pass_fd'])<flux:table.column>Pass 1D</flux:table.column>@endif
                    @if($selectedMetrics['pass_cmp_40p'])<flux:table.column>Cmp 40+</flux:table.column>@endif
                    @if($selectedMetrics['pass_2pt'])<flux:table.column>Pass 2pt</flux:table.column>@endif
                    @if($selectedMetrics['pass_int_td'])<flux:table.column>Pick Six</flux:table.column>@endif

                    @if($selectedMetrics['cmp_pct'])<flux:table.column>Cmp %</flux:table.column>@endif
                    @if($selectedMetrics['def_fum_td'])<flux:table.column>DEF Fum TD</flux:table.column>@endif
                    @if($selectedMetrics['fum'])<flux:table.column>Fum</flux:table.column>@endif
                    @if($selectedMetrics['fum_lost'])<flux:table.column>Fum Lost</flux:table.column>@endif
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

                            @if($selectedMetrics['position_rank_2024'])
                            <flux:table.cell>
                                @if (isset($player->season_2024_summary) && isset($player->season_2024_summary['position_rank']) && $player->season_2024_summary['position_rank'])
                                    <flux:badge variant="secondary" color="purple">{{ $player->position }}{{ $player->season_2024_summary['position_rank'] }}</flux:badge>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['snap_pct_2024'])
                            <flux:table.cell>
                                @if (isset($player->season_2024_summary) && isset($player->season_2024_summary['snap_percentage_avg']) && $player->season_2024_summary['snap_percentage_avg'] !== null)
                                    <span class="font-medium text-blue-600">{{ number_format($player->season_2024_summary['snap_percentage_avg'], 1) }}%</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['target_share_2024'])
                            <flux:table.cell>
                                @if (!is_null($player->season_2024_target_share_avg))
                                    <span class="font-medium text-purple-600">{{ number_format($player->season_2024_target_share_avg, 1) }}%</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['stddev_above'])
                            <flux:table.cell>
                                @if (isset($player->season_2024_summary) && isset($player->season_2024_summary['stddev_above']) && $player->season_2024_summary['stddev_above'] > 0)
                                    <span class="font-medium text-green-700">{{ number_format($player->season_2024_summary['stddev_above'], 1) }}</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['stddev_below'])
                            <flux:table.cell>
                                @if (isset($player->season_2024_summary) && isset($player->season_2024_summary['stddev_below']))
                                    @if($player->season_2024_summary['stddev_below'] > 0)
                                        <span class="font-medium text-green-500">{{ number_format($player->season_2024_summary['stddev_below'], 1) }}</span>
                                    @else
                                        <span class="font-medium text-red-500">{{ number_format($player->season_2024_summary['stddev_below'], 1) }}</span>
                                    @endif
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

                            @if($selectedMetrics['weekly_position_rank'])
                            <flux:table.cell>
                                @if ($player->weekly_position_rank)
                                    <flux:badge variant="secondary" color="purple">{{ $player->position }}{{ $player->weekly_position_rank }}</flux:badge>
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

                            @if($selectedMetrics['rec'])<flux:table.cell>{{ $val('rec') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rec_tgt'])<flux:table.cell>{{ $val('rec_tgt') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rec_yd'])<flux:table.cell>{{ $val('rec_yd') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rec_td'])<flux:table.cell>{{ $val('rec_td') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rec_fd'])<flux:table.cell>{{ $val('rec_fd') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rec_2pt'])<flux:table.cell>{{ $val('rec_2pt') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rec_0_4'])<flux:table.cell>{{ $val('rec_0_4') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rec_5_9'])<flux:table.cell>{{ $val('rec_5_9') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rec_10_19'])<flux:table.cell>{{ $val('rec_10_19') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rec_20_29'])<flux:table.cell>{{ $val('rec_20_29') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rec_30_39'])<flux:table.cell>{{ $val('rec_30_39') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rec_40p'])<flux:table.cell>{{ $val('rec_40p') ?? '-' }}</flux:table.cell>@endif

                            @if($selectedMetrics['rush_att'])<flux:table.cell>{{ $val('rush_att') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rush_yd'])<flux:table.cell>{{ $val('rush_yd') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rush_td'])<flux:table.cell>{{ $val('rush_td') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rush_fd'])<flux:table.cell>{{ $val('rush_fd') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['rush_40p'])<flux:table.cell>{{ $val('rush_40p') ?? '-' }}</flux:table.cell>@endif

                            @if($selectedMetrics['pass_att'])<flux:table.cell>{{ $val('pass_att') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_cmp'])<flux:table.cell>{{ $val('pass_cmp') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_yd'])<flux:table.cell>{{ $val('pass_yd') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_td'])<flux:table.cell>{{ $val('pass_td') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_int'])<flux:table.cell>{{ $val('pass_int') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_inc'])<flux:table.cell>{{ $val('pass_inc') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_sack'])<flux:table.cell>{{ $val('pass_sack') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_fd'])<flux:table.cell>{{ $val('pass_fd') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_cmp_40p'])<flux:table.cell>{{ $val('pass_cmp_40p') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_2pt'])<flux:table.cell>{{ $val('pass_2pt') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['pass_int_td'])<flux:table.cell>{{ $val('pass_int_td') ?? '-' }}</flux:table.cell>@endif

                            @if($selectedMetrics['cmp_pct'])<flux:table.cell>{{ !is_null($cmpPct) ? number_format($cmpPct, 1).'%' : '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['def_fum_td'])<flux:table.cell>{{ $val('def_fum_td') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['fum'])<flux:table.cell>{{ $val('fum') ?? '-' }}</flux:table.cell>@endif
                            @if($selectedMetrics['fum_lost'])<flux:table.cell>{{ $val('fum_lost') ?? '-' }}</flux:table.cell>@endif

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
                                    No players found matching your filters.
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
