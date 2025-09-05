<?php

use App\Models\Player;
use App\MCP\Tools\FetchUserLeaguesTool;
use App\MCP\Tools\FetchRosterTool;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $position = '';
    public string $team = '';
    public string $selectedLeagueId = '';
    public string $adpRange = '';
    public string $injuryStatus = '';
    public string $sortBy = 'last_name';
    public array $leagues = [];
    public array $leagueRosters = [];
    public bool $loadingLeagues = false;
    public ?int $resolvedWeek = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'position' => ['except' => ''],
        'team' => ['except' => ''],
        'selectedLeagueId' => ['except' => ''],
        'adpRange' => ['except' => ''],
        'injuryStatus' => ['except' => ''],
        'sortBy' => ['except' => 'last_name'],
    ];

    public function mount(): void
    {
        $this->resolveCurrentWeek();
        $this->loadUserLeagues();
    }

    public function loadUserLeagues(): void
    {
        $this->loadingLeagues = true;

        try {
            $user = Auth::user();

            if (!$user || (!$user->sleeper_username && !$user->sleeper_user_id)) {
                $this->leagues = [];
                $this->loadingLeagues = false;
                return;
            }

            // Determine user identifier
            $userIdentifier = $user->sleeper_username ?: $user->sleeper_user_id;

            // Fetch user's leagues with caching
            $cacheKey = "user_leagues_{$userIdentifier}";
            $leaguesResult = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($userIdentifier) {
                try {
                    $leaguesTool = app(FetchUserLeaguesTool::class);
                    return $leaguesTool->execute([
                        'user_identifier' => $userIdentifier,
                        'sport' => 'nfl',
                    ]);
                } catch (\Exception $e) {
                    return ['success' => false, 'data' => []];
                }
            });

            if ($leaguesResult['success'] ?? false) {
                $this->leagues = $leaguesResult['data'] ?? [];
            } else {
                $this->leagues = [];
            }
        } catch (\Exception $e) {
            $this->leagues = [];
            logger('Failed to load user leagues', ['error' => $e->getMessage()]);
        } finally {
            $this->loadingLeagues = false;
        }
    }

    public function updatedSelectedLeagueId(): void
    {
        $this->loadLeagueRosters();
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPosition(): void
    {
        $this->resetPage();
    }

    public function updatedTeam(): void
    {
        $this->resetPage();
    }

    public function updatedAdpRange(): void
    {
        $this->resetPage();
    }

    public function updatedInjuryStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    private function loadLeagueRosters(): void
    {
        if (empty($this->selectedLeagueId)) {
            $this->leagueRosters = [];
            return;
        }

        try {
            // Fetch rosters for the selected league with caching
            $cacheKey = "league_rosters_{$this->selectedLeagueId}";
            $rostersResult = Cache::remember($cacheKey, now()->addMinutes(10), function () {
                try {
                    $rostersTool = app(FetchRosterTool::class);
                    return $rostersTool->execute([
                        'league_id' => $this->selectedLeagueId,
                        'include_player_details' => false,
                        'include_owner_details' => true,
                    ]);
                } catch (\Exception $e) {
                    return ['success' => false, 'data' => []];
                }
            });

            if ($rostersResult['success'] ?? false) {
                $this->leagueRosters = $rostersResult['data'] ?? [];
            } else {
                $this->leagueRosters = [];
            }
        } catch (\Exception $e) {
            $this->leagueRosters = [];
            logger('Failed to load league rosters', ['error' => $e->getMessage(), 'league_id' => $this->selectedLeagueId]);
        }
    }

    private function resolveCurrentWeek(): void
    {
        try {
            $response = Sleeper::state()->current('nfl');
            if ($response->successful()) {
                $state = $response->json();
                $week = isset($state['week']) ? (int) $state['week'] : null;
                $this->resolvedWeek = ($week && $week >= 1 && $week <= 18) ? $week : null;
            }
        } catch (\Throwable $e) {
            $this->resolvedWeek = null;
        }
    }

    private function getPlayerLeagueStatus(string $playerId): array
    {
        if (empty($this->selectedLeagueId) || empty($this->leagueRosters)) {
            return ['status' => 'free_agent', 'team_name' => null];
        }

        foreach ($this->leagueRosters as $roster) {
            $players = array_merge(
                $roster['starters'] ?? [],
                $roster['reserve'] ?? [],
                $roster['taxi'] ?? []
            );

            if (in_array($playerId, $players)) {
                return [
                    'status' => 'owned',
                    'team_name' => $roster['owner']['team_name'] ?? 'Unknown Team'
                ];
            }
        }

        return ['status' => 'free_agent', 'team_name' => null];
    }

    public function getPlayersQuery()
    {
        return Player::query()
            ->whereIn('position', ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('first_name', 'like', '%' . $this->search . '%')
                      ->orWhere('last_name', 'like', '%' . $this->search . '%')
                      ->orWhere('full_name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->position, function ($query) {
                $query->where('position', $this->position);
            })
            ->when($this->team, function ($query) {
                $query->where('team', $this->team);
            })
            ->when($this->adpRange, function ($query) {
                switch ($this->adpRange) {
                    case '1-5':
                        $query->whereBetween('adp', [1, 5]);
                        break;
                    case '6-10':
                        $query->whereBetween('adp', [6, 10]);
                        break;
                    case '11-15':
                        $query->whereBetween('adp', [11, 15]);
                        break;
                    case '16-20':
                        $query->whereBetween('adp', [16, 20]);
                        break;
                    case '21-30':
                        $query->whereBetween('adp', [21, 30]);
                        break;
                    case '31-50':
                        $query->whereBetween('adp', [31, 50]);
                        break;
                    case '51-75':
                        $query->whereBetween('adp', [51, 75]);
                        break;
                    case '76-100':
                        $query->whereBetween('adp', [76, 100]);
                        break;
                    case '100+':
                        $query->where('adp', '>=', 100);
                        break;
                }
            })
            ->when($this->injuryStatus, function ($query) {
                switch ($this->injuryStatus) {
                    case 'healthy':
                        $query->where(function ($q) {
                            $q->whereNull('injury_status')
                              ->orWhere('injury_status', 'Healthy');
                        });
                        break;
                    case 'injured':
                        $query->whereNotNull('injury_status')
                              ->where('injury_status', '!=', 'Healthy');
                        break;
                    case 'questionable':
                        $query->where('injury_status', 'Questionable');
                        break;
                    case 'doubtful':
                        $query->where('injury_status', 'Doubtful');
                        break;
                    case 'out':
                        $query->where('injury_status', 'Out');
                        break;
                }
            })
            ->where('active', true)
            ->when($this->sortBy === 'adp', function ($query) {
                $query->orderBy('adp', 'asc')->orderBy('last_name', 'asc');
            }, function ($query) {
                $query->orderBy($this->sortBy === 'position' ? 'position' : 'last_name', 'asc');
            });
    }

    public function getAvailablePositions(): array
    {
        return ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'];
    }

    public function getAvailableTeams(): array
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
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="lg">Players</flux:heading>
            <p class="text-muted-foreground mt-1">Browse and filter fantasy football players</p>
        </div>

        @if ($resolvedWeek)
            <flux:badge variant="secondary" size="sm">
                NFL Week {{ $resolvedWeek }}
            </flux:badge>
        @endif
    </div>

    <!-- Filters -->
    <x-player-filters
        :search="$search"
        :position="$position"
        :team="$team"
        :adpRange="$adpRange"
        :injuryStatus="$injuryStatus"
        :selectedLeagueId="$selectedLeagueId"
        :leagues="$leagues"
        :loadingLeagues="$loadingLeagues"
        :availablePositions="$this->getAvailablePositions()"
        :availableTeams="$this->getAvailableTeams()"
    />

    <!-- Results Summary -->
    <div class="flex items-center justify-between">
        <div class="text-sm text-muted-foreground">
            Showing {{ $this->getPlayersQuery()->paginate(24)->count() }} of {{ $this->getPlayersQuery()->count() }} players
        </div>

        <div class="flex items-center gap-2">
            <span class="text-sm text-muted-foreground">Sort by:</span>
            <flux:select wire:model.live="sortBy" class="w-40">
                <option value="last_name">Name (A-Z)</option>
                <option value="adp">ADP (Low to High)</option>
                <option value="position">Position</option>
                <option value="team">Team</option>
            </flux:select>
        </div>
    </div>

    <!-- Players Grid -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($this->getPlayersQuery()->paginate(24) as $player)
            @php
                $leagueStatus = $this->getPlayerLeagueStatus($player->player_id);
            @endphp

            <x-player-card
                :player="$player"
                :leagueStatus="$leagueStatus"
                :showDetailed="false"
            />
        @empty
            <div class="col-span-full">
                <div class="text-center py-12">
                    <div class="text-muted-foreground">
                        <svg class="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h3 class="text-lg font-medium mb-2">No players found</h3>
                        <p>Try adjusting your filters to see more players.</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if ($this->getPlayersQuery()->paginate(24)->hasPages())
        <div class="flex justify-center pt-6">
            {{ $this->getPlayersQuery()->paginate(24)->links() }}
        </div>
    @endif
</div>
