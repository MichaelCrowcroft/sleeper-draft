<?php

use App\Actions\Matchups\EnrichMatchupsWithPlayerData;
use App\Actions\Matchups\FilterMatchups;
use App\Actions\Matchups\GetMatchupsWithOwners;
use App\Actions\Sleeper\GetSeasonState;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public string $league_id;
    public ?int $week = null;

    public function mount(string $league_id, ?int $week = null): void
    {
        $this->league_id = $league_id;
        $this->week = $week;
        if($this->week === null) {
            $this->week = new GetSeasonState('nfl')->execute()['week'];
        }
    }

    #[Computed]
    public function matchup(): array
    {
        $matchups = new GetMatchupsWithOwners()->execute($this->league_id, $this->week);
        $matchups = new FilterMatchups()->execute($matchups, Auth::user()->sleeper_user_id);
        $matchups = new EnrichMatchupsWithPlayerData()->execute($matchups, 2025, $this->week);

        // Calculate projected totals for each team
        foreach ($matchups as &$matchup) {
            foreach ($matchup as &$team) {
                $team['projected_total'] = $this->calculateProjectedTotal($team);
            }
        }

        return $matchups;
    }

    private function calculateProjectedTotal(array $team): float
    {
        $total = 0.0;

        // Get all players (starters + bench)
        $allPlayers = array_merge(
            $team['starters'] ?? [],
            $team['players'] ?? []
        );

        foreach ($allPlayers as $player) {
            if (!is_array($player)) {
                continue;
            }

            // Use actual points if available, otherwise use projected points
            if (isset($player['stats']['stats']['pts_ppr']) && $player['stats']['stats']['pts_ppr'] !== null) {
                $total += (float) $player['stats']['stats']['pts_ppr'];
            } elseif (isset($player['projection']['stats']['pts_ppr']) && $player['projection']['stats']['pts_ppr'] !== null) {
                $total += (float) $player['projection']['stats']['pts_ppr'];
            }
        }

        return $total;
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">Matchups</flux:heading>
            <p class="text-muted-foreground">Week {{ $this->week }} • League {{ $this->league_id }}</p>
        </div>
    </div>

    @if(empty($this->matchup))
        <div class="text-center py-12">
            <flux:heading size="lg" class="text-muted-foreground">No matchup data available</flux:heading>
            <p class="text-muted-foreground mt-2">Matchup data will appear once the week begins.</p>
        </div>
    @else
        @foreach($this->matchup as $matchupId => $teams)
            <div class="bg-card rounded-lg border p-6 mb-6">
                <!-- Matchup Header -->
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg">Matchup #{{ $matchupId }}</flux:heading>
                    <div class="text-sm text-muted-foreground">
                        Week {{ $this->week }}
                    </div>
                </div>

                <!-- Teams Side by Side -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    @foreach($teams as $index => $team)
                        <div class="space-y-4">
                            <!-- Team Header -->
                            <div class="flex items-center justify-between p-4 bg-muted/50 rounded-lg">
                                <div>
                                    <h3 class="font-semibold text-lg">{{ $team['owner_name'] ?? $team['owner_id'] ?? 'Unknown Owner' }}</h3>
                                    @if(isset($team['roster_settings']['name']) && !empty($team['roster_settings']['name']))
                                        <p class="text-sm text-muted-foreground">{{ $team['roster_settings']['name'] }}</p>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <div class="flex items-center gap-4">
                                        <div>
                                            <div class="text-lg font-bold text-green-600">{{ number_format($team['projected_total'] ?? 0, 1) }}</div>
                                            <div class="text-xs text-muted-foreground">Projected</div>
                                        </div>
                                        <div class="border-l border-muted-foreground/20 pl-4">
                                            <div class="text-2xl font-bold text-blue-600">{{ number_format($team['points'] ?? 0, 1) }}</div>
                                            <div class="text-xs text-muted-foreground">Actual</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Players -->
                            <div class="space-y-3">
                                <h4 class="font-medium text-sm text-muted-foreground uppercase tracking-wide">Starters</h4>
                                @if(isset($team['starters']) && is_array($team['starters']) && !empty($team['starters']))
                                    @foreach($team['starters'] as $player)
                                        @if(is_array($player))
                                            @include('components.matchup-player-card', ['player' => $player])
                                        @endif
                                    @endforeach
                                @else
                                    <div class="text-center py-4 text-muted-foreground">
                                        No starters available
                                    </div>
                                @endif

                                @if(isset($team['players']) && is_array($team['players']) && !empty($team['players']))
                                    <div class="pt-3 border-t">
                                        <h4 class="font-medium text-sm text-muted-foreground uppercase tracking-wide mb-3">Bench</h4>
                                        @foreach($team['players'] as $player)
                                            @if(is_array($player))
                                                @include('components.matchup-player-card', ['player' => $player, 'isBench' => true])
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</section>
