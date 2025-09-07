<?php

use App\Models\Player;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

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
        'stddev_above' => true,
        'stddev_below' => true,
        'proj_ppg_2025' => true,
        'owner' => true,
        'status' => true,
        // Additional metrics (default off)
        'proj_pts_week' => false,
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

    public function mount()
    {
        $this->search = request('search', '');
        $this->position = request('position', '');
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

        // Get position rankings for 2024 season
        $positionRankings = Player::calculatePositionRankings2024();
        $rankingsLookup = [];
        foreach ($positionRankings as $position => $rankedPlayers) {
            foreach ($rankedPlayers as $rankedPlayer) {
                $rankingsLookup[$rankedPlayer['player_id']] = $rankedPlayer['rank'];
            }
        }

        // Add player stats and roster information for each player
        foreach ($players as $player) {
            $player->season_2024_summary = $player->getSeason2024Summary();
            $player->season_2025_projections = $player->getSeason2025ProjectionSummary();

            // Add position ranking to the array (create a new array to avoid overloaded property error)
            $summaryWithRank = $player->season_2024_summary;
            $summaryWithRank['position_rank'] = $rankingsLookup[$player->player_id] ?? null;
            $player->season_2024_summary = $summaryWithRank;

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
                </div>

                <div class="mt-4">
                    <flux:heading size="xs">2025 Projection Averages (per game)</flux:heading>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mt-2">
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

                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rush_att" /><span>Rush Attempts</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rush_yd" /><span>Rushing Yards</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rush_td" /><span>Rushing TDs</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rush_fd" /><span>Rushing First Downs</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.rush_40p" /><span>Rush 40+ Yds</span></label>

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
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.def_fum_td" /><span>Defense Fumble TDs</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.fum" /><span>Fumbles</span></label>
                        <label class="flex items-center gap-2 text-sm"><flux:switch wire:model.live="selectedMetrics.fum_lost" /><span>Fumbles Lost</span></label>
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