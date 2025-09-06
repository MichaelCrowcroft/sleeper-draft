<?php

use App\Actions\Matchups\DetermineCurrentWeek;
use App\Actions\Sleeper\FetchUserLeagues;
use App\Models\User;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public array $leagues = [];

    public function mount(): void
    {
        $user = Auth::user();

        if($user->sleeper_user_id) {
            $this->leagues = app(FetchUserLeagues::class)
                ->execute($user->sleeper_user_id, 'nfl', '2025');
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
