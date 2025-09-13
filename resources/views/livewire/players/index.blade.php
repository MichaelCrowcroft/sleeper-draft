<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use App\Actions\Sleeper\DetermineCurrentWeek;
use App\Actions\Players\AvailablePositions;
use App\Actions\Players\AvailableTeams;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;
use App\Actions\Players\GetRosteredPlayers;
use App\Models\Player;
use App\Models\PlayerStats;
use App\Actions\Players\AddOwnerToPlayers;

new class extends Component
{
    #[Url]
    public ?string $search = '';

    #[Url]
    public ?string $position = '';

    #[Url]
    public ?string $team = '';

    #[Url]
    public ?string $selected_league_id = '';

    #[Url]
    public ?bool $fa_only = false;

    public $selectedMetrics = [
        // Core columns shown on this page
        'age' => true,
        'adp' => true,
        'owner' => true,
        'status' => true,

        // Additional metrics referenced in the row cells (disabled by default on this page)
        'avg_ppg_2024' => true,
        'position_rank_2024' => true,
        'snap_pct_2024' => true,
        'target_share_2024' => true,
        'stddev_above' => true,
        'stddev_below' => true,
        'proj_pts_week' => true,
        'weekly_position_rank' => false,

        // 2025 projection averages (per-game) keys
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

    #[Computed]
    public function players()
    {
        $rostered_players = new GetRosteredPlayers()->execute($this->selected_league_id);
        $excluded_player_ids = $this->fa_only ? array_keys($rostered_players) : [];
        $state = new DetermineCurrentWeek()->execute('nfl');

        $players = Player::query()
            ->where('active', true)
            ->when($this->position, fn($q) => $q->where('position', $this->position))
            ->when($this->team, fn($q) => $q->where('team', $this->team))
            ->when($this->search, fn($q) => $q->search($this->search))
            ->whereNotIn('player_id', $excluded_player_ids)
            ->select('players.*')
            ->selectSub(PlayerStats::query()
                ->select('weekly_ranking')
                ->whereColumn('player_stats.player_id', 'players.player_id')
                ->where('season', $state['season'])
                ->where('week', $state['week'])
                ->limit(1),
                'weekly_position_rank'
            )->playablePositions()
            ->with(['projections2025', 'seasonSummaries'])
            ->orderByAdp()
            ->paginate(25);

        $players = new AddOwnerToPlayers()->execute($players, $rostered_players);

        return $players;
    }

    #[Computed]
    public function availablePositions(): array
    {
        return new AvailablePositions()->execute();
    }

    #[Computed]
    public function availableTeams(): array
    {
        return new AvailableTeams()->execute();
    }

    #[Computed]
    public function leagues(): array
    {
        return Sleeper::user(Auth::user()->sleeper_user_id)
            ->leagues('nfl', date('Y'))
            ->json();
    }

    #[Computed]
    public function colspan(): int
    {
        // Base columns that are always shown: Player, Pos, Team, Actions
        $colspan = 4;

        // Count all enabled metric columns
        foreach ($this->selectedMetrics as $enabled) {
            if ($enabled === true) {
                $colspan++;
            }
        }

        return $colspan;
    }

    public function mount()
    {
        if(!$this->selected_league_id) {
            $this->selected_league_id = $this->leagues[0]['league_id'] ?? '';
        }
    }

    #[Computed]
    public function resolvedWeek(): ?int
    {
        $state = new DetermineCurrentWeek()->execute('nfl');

        return $state['week'] ?? null;
    }
}; ?>

<section class="w-full max-w-none">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">Players</flux:heading>
                <p class="text-muted-foreground mt-1">Browse and filter fantasy football players</p>
            </div>
            <div class="hidden md:flex items-center gap-2"></div>
        </div>

        @if ($this->resolvedWeek)
            <flux:callout>
                NFL Week {{ $this->resolvedWeek }}
            </flux:callout>
        @endif

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
                            @foreach ($this->availablePositions as $position)
                                <flux:select.option value="{{ $position }}">{{ $position }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <!-- Team Filter -->
                    <div>
                        <flux:select wire:model.live="team">
                            <flux:select.option value="">All Teams</flux:select.option>
                            @foreach ($this->availableTeams as $team)
                                <flux:select.option value="{{ $team }}">{{ $team }}</flux:select.option>
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
                        <flux:select wire:model.live="selected_league_id">
                            <flux:select.option value="">No League Selected</flux:select.option>
                            @foreach ($this->leagues as $league)
                                <flux:select.option value="{{ $league['league_id'] }}">{{ $league['name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <!-- Free Agents Only -->
                    @if($selected_league_id)
                        <div>
                            <label class="flex items-center gap-2 text-sm">
                                <flux:switch wire:model.live="fa_only" />
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



        <!-- Players Table (generic) -->
        <flux:callout class="overflow-x-auto">
            <flux:heading size="md" class="mb-4">Player Data</flux:heading>
            <div class="w-full max-w-full">
                <flux:table :paginate="$this->players" class="min-w-max">
                <flux:table.columns>
                    <flux:table.column
                        class="cursor-pointer"
                    >
                        Player
                    </flux:table.column>
                    <flux:table.column
                        class="cursor-pointer"
                    >
                        Pos
                    </flux:table.column>
                    <flux:table.column
                        class="cursor-pointer"
                    >
                        Team
                    </flux:table.column>
                    @if($selectedMetrics['age'])
                    <flux:table.column
                        class="cursor-pointer"
                    >
                        Age
                    </flux:table.column>
                    @endif
                    @if($selectedMetrics['adp'])
                    <flux:table.column
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
                                @php
                                    $summary = optional($player->seasonSummaries->firstWhere('season', 2024));
                                @endphp
                                @if ($summary && $summary->average_points_per_game > 0)
                                    <span class="font-medium text-green-600">{{ number_format($summary->average_points_per_game, 1) }}</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['position_rank_2024'])
                            <flux:table.cell>
                                @php
                                    $summary = optional($player->seasonSummaries->firstWhere('season', 2024));
                                @endphp
                                @if ($summary && $summary->position_rank)
                                    <flux:badge variant="secondary" color="purple">{{ $player->position }}{{ $summary->position_rank }}</flux:badge>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['snap_pct_2024'])
                            <flux:table.cell>
                                @php
                                    $summary = optional($player->seasonSummaries->firstWhere('season', 2024));
                                @endphp
                                @if ($summary && $summary->snap_percentage_avg !== null)
                                    <span class="font-medium text-blue-600">{{ number_format($summary->snap_percentage_avg, 1) }}%</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['target_share_2024'])
                            <flux:table.cell>
                                @php
                                    $summary = optional($player->seasonSummaries->firstWhere('season', 2024));
                                @endphp
                                @if ($summary && $summary->target_share_avg !== null)
                                    <span class="font-medium text-purple-600">{{ number_format($summary->target_share_avg, 1) }}%</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['stddev_above'])
                            <flux:table.cell>
                                @php
                                    $summary = optional($player->seasonSummaries->firstWhere('season', 2024));
                                @endphp
                                @if ($summary && $summary->stddev_above > 0)
                                    <span class="font-medium text-green-700">{{ number_format($summary->stddev_above, 1) }}</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['stddev_below'])
                            <flux:table.cell>
                                @php
                                    $summary = optional($player->seasonSummaries->firstWhere('season', 2024));
                                @endphp
                                @if ($summary && $summary->stddev_below !== null)
                                    @if($summary->stddev_below > 0)
                                        <span class="font-medium text-green-500">{{ number_format($summary->stddev_below, 1) }}</span>
                                    @else
                                        <span class="font-medium text-red-500">{{ number_format($summary->stddev_below, 1) }}</span>
                                    @endif
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </flux:table.cell>
                            @endif

                            @if($selectedMetrics['proj_pts_week'])
                            <flux:table.cell>
                                @php
                                    $projPts = $this->resolvedWeek ? $player->getProjectedPointsForWeek(2025, (int) $this->resolvedWeek) : null;
                                @endphp
                                @if (!is_null($projPts))
                                    <span class="font-medium text-blue-700">{{ number_format($projPts, 1) }}</span>
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
                                @php $owner = $player->owner_or_free_agent ?? null; @endphp
                                @if ($owner)
                                    <flux:badge variant="secondary" color="blue">{{ $owner }}</flux:badge>
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
                                $avg = $player->getSeason2025ProjectionsAverages();
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
                            <flux:table.cell :colspan="$this->colspan" align="center">
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
