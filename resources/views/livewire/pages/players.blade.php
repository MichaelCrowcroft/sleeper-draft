<?php

use App\Models\Player;
use App\MCP\Tools\FetchUserLeaguesTool;
use App\MCP\Tools\FetchRosterTool;
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
    public array $leagues = [];
    public array $leagueRosters = [];
    public bool $loadingLeagues = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'position' => ['except' => ''],
        'team' => ['except' => ''],
        'selectedLeagueId' => ['except' => ''],
    ];

    public function mount(): void
    {
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
            ->where('active', true)
            ->orderBy('last_name');
    }

    public function getAvailablePositions(): array
    {
        return Cache::remember('player_positions', now()->addHours(1), function () {
            return Player::whereNotNull('position')
                ->where('active', true)
                ->distinct()
                ->pluck('position')
                ->sort()
                ->values()
                ->toArray();
        });
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
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="lg">Players</flux:heading>
            <p class="text-muted-foreground mt-1">Browse and filter fantasy football players</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="space-y-4">
        <flux:callout>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
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
                        <option value="">All Positions</option>
                        @foreach ($this->getAvailablePositions() as $pos)
                            <option value="{{ $pos }}">{{ $pos }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Team Filter -->
                <div>
                    <flux:select wire:model.live="team">
                        <option value="">All Teams</option>
                        @foreach ($this->getAvailableTeams() as $teamCode)
                            <option value="{{ $teamCode }}">{{ $teamCode }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- League Selection -->
                <div>
                    <flux:select wire:model.live="selectedLeagueId">
                        <option value="">No League Selected</option>
                        @if ($loadingLeagues)
                            <option disabled>Loading leagues...</option>
                        @else
                            @foreach ($leagues as $league)
                                <option value="{{ $league['id'] }}">{{ $league['name'] }}</option>
                            @endforeach
                        @endif
                    </flux:select>
                </div>
            </div>
        </flux:callout>

        <!-- Active Filters Summary -->
        @if ($search || $position || $team || $selectedLeagueId)
            <div class="flex flex-wrap gap-2">
                @if ($search)
                    <flux:badge variant="outline">
                        Search: {{ $search }}
                        <button wire:click="$set('search', '')" class="ml-1 hover:text-destructive">×</button>
                    </flux:badge>
                @endif
                @if ($position)
                    <flux:badge variant="outline">
                        Position: {{ $position }}
                        <button wire:click="$set('position', '')" class="ml-1 hover:text-destructive">×</button>
                    </flux:badge>
                @endif
                @if ($team)
                    <flux:badge variant="outline">
                        Team: {{ $team }}
                        <button wire:click="$set('team', '')" class="ml-1 hover:text-destructive">×</button>
                    </flux:badge>
                @endif
                @if ($selectedLeagueId)
                    @php
                        $selectedLeague = collect($leagues)->firstWhere('id', $selectedLeagueId);
                    @endphp
                    <flux:badge variant="outline">
                        League: {{ $selectedLeague['name'] ?? 'Unknown' }}
                        <button wire:click="$set('selectedLeagueId', '')" class="ml-1 hover:text-destructive">×</button>
                    </flux:badge>
                @endif
            </div>
        @endif
    </div>

    <!-- Players Table -->
    <div class="rounded-lg border bg-card">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b bg-muted/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium">Player</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Position</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Team</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">ADP</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Injury Status</th>
                        @if ($selectedLeagueId)
                            <th class="px-4 py-3 text-left text-sm font-medium">League Status</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($this->getPlayersQuery()->paginate(25) as $player)
                        @php
                            $leagueStatus = $this->getPlayerLeagueStatus($player->player_id);
                        @endphp
                        <tr class="hover:bg-muted/50">
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ $player->first_name }} {{ $player->last_name }}</span>
                                    @if ($player->age)
                                        <span class="text-xs text-muted-foreground">{{ $player->age }} years old</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge variant="secondary">{{ $player->position }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge variant="outline">{{ $player->team }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if ($player->adp_formatted)
                                    {{ $player->adp_formatted }}
                                @elseif ($player->adp)
                                    {{ number_format($player->adp, 1) }}
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($player->injury_status && $player->injury_status !== 'Healthy')
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium text-red-600">{{ $player->injury_status }}</span>
                                        @if ($player->injury_body_part)
                                            <span class="text-xs text-muted-foreground">{{ $player->injury_body_part }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-sm text-green-600">Healthy</span>
                                @endif
                            </td>
                            @if ($selectedLeagueId)
                                <td class="px-4 py-3">
                                    @if ($leagueStatus['status'] === 'owned')
                                        <flux:badge variant="default">{{ $leagueStatus['team_name'] }}</flux:badge>
                                    @else
                                        <span class="text-sm text-muted-foreground">Free Agent</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $selectedLeagueId ? 6 : 5 }}" class="px-4 py-8 text-center text-muted-foreground">
                                No players found matching your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if ($this->getPlayersQuery()->paginate(25)->hasPages())
            <div class="border-t px-4 py-3">
                {{ $this->getPlayersQuery()->paginate(25)->links() }}
            </div>
        @endif
    </div>

    <!-- Results Summary -->
    <div class="text-sm text-muted-foreground">
        Showing {{ $this->getPlayersQuery()->paginate(25)->count() }} of {{ $this->getPlayersQuery()->count() }} players
    </div>
</div>
