<?php

use App\Actions\Matchups\DetermineCurrentWeek;
use App\Actions\Sleeper\FetchUserLeagues;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public array $leagues = [];

    public function mount(): void
    {
        $auth = Auth::user();
        $sleeperUserId = $auth->sleeper_user_id ?? null;
        $state = app(DetermineCurrentWeek::class)->execute('nfl');
        $season = $state['season'];

        if ($sleeperUserId) {
            $this->leagues = app(FetchUserLeagues::class)->execute((string) $sleeperUserId, 'nfl', $season);
        } else {
            $this->leagues = [];
        }
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-4">
        <div>
            <flux:heading size="xl">Leagues</flux:heading>
            <p class="text-muted-foreground">Your Sleeper leagues for this season</p>
        </div>
    </div>

    @if (empty($leagues))
        <flux:callout variant="subtle">No leagues found. Link your Sleeper account in Settings.</flux:callout>
    @else
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($leagues as $league)
                <flux:callout>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-semibold">{{ $league['name'] ?? 'League' }}</div>
                            <div class="text-xs text-muted-foreground">{{ $league['league_id'] ?? '' }}</div>
                        </div>
                        <flux:button href="{{ route('matchups.show.current', ['leagueId' => $league['league_id'] ?? '']) }}" wire:navigate variant="primary" size="sm">
                            View Matchup
                        </flux:button>
                    </div>
                </flux:callout>
            @endforeach
        </div>
    @endif
</section>
