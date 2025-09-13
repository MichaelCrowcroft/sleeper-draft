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

        return $matchups;
    }

    public function getMatchupInsights(array $matchup): array
    {
        $teams = array_values($matchup);
        $teamA = $teams[0] ?? null;
        $teamB = $teams[1] ?? null;

        $probA = $matchup['win_probabilities']['team_a_win_probability'] ?? 0;
        $probB = $matchup['win_probabilities']['team_b_win_probability'] ?? 0;

        $favored = $probA > $probB ? ($teamA['owner_name'] ?? 'Team A') : ($teamB['owner_name'] ?? 'Team B');
        $confidence = max($probA, $probB);

        return [
            'favored_team' => $favored,
            'win_probability' => $confidence,
        ];
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

                <!-- Matchup Summary with Confidence Intervals and Win Probabilities -->
                @if(isset($teams['win_probabilities']))
                <div class="bg-gradient-to-r from-green-50 to-blue-50 dark:from-green-950/20 dark:to-blue-950/20 rounded-lg p-6 mb-6 border border-green-200 dark:border-green-800">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        @php $teamIndex = 0; @endphp
                        @foreach($teams as $index => $team)
                            @if(is_array($team) && isset($team['owner_id']))
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-semibold text-lg">{{ $team['owner_name'] ?? $team['owner_id'] ?? 'Unknown Owner' }}</h3>
                                    <div class="text-right">
                                        @if($teamIndex === 0)
                                            <div class="text-2xl font-bold text-green-600">{{ $teams['win_probabilities']['team_a_win_probability'] }}%</div>
                                            <div class="text-xs text-muted-foreground">Win Probability</div>
                                        @else
                                            <div class="text-2xl font-bold text-blue-600">{{ $teams['win_probabilities']['team_b_win_probability'] }}%</div>
                                            <div class="text-xs text-muted-foreground">Win Probability</div>
                                        @endif
                                    </div>
                                </div>

                                @if(isset($team['confidence_interval']))
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border">
                                    <div class="text-center">
                                        <div class="text-sm text-muted-foreground mb-1">90% Confidence Range</div>
                                        <div class="text-xl font-bold text-gray-900 dark:text-gray-100">
                                            {{ $team['confidence_interval']['lower_90'] }} - {{ $team['confidence_interval']['upper_90'] }}
                                        </div>
                                        <div class="text-xs text-muted-foreground mt-1">
                                            ±{{ number_format($team['confidence_interval']['confidence_range'] / 2, 1) }} pts
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>
                            @php $teamIndex++; @endphp
                            @endif
                        @endforeach
                    </div>

                    <!-- Matchup Insights -->
                    <div class="mt-4 pt-4 border-t border-green-200 dark:border-green-700">
                        <div class="text-center text-sm text-muted-foreground">
                            @php $insights = $this->getMatchupInsights($teams) @endphp
                            <span class="font-medium">{{ $insights['favored_team'] }}</span> has a <span class="font-medium">{{ $insights['win_probability'] }}%</span> chance to win this matchup
                        </div>
                    </div>
                </div>
                @endif

                <!-- Teams Side by Side -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    @foreach($teams as $index => $team)
                        @if(is_array($team) && isset($team['owner_id']))
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
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</section>
