<?php

// use App\Actions\Sleeper\DetermineCurrentWeek; // not used here
use App\Models\User;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

new class extends Component
{
    public array $leagues = [];

    public function mount(): void
    {
        $user = Auth::user();

        if($user->sleeper_user_id) {
            $this->leagues = Sleeper::user($user->sleeper_user_id)
                ->leagues('nfl', '2025')
                ->json();
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
                        <div class="flex gap-2">
                            <flux:button href="{{ route('matchups.show.current', ['league_id' => $league['league_id'] ?? '']) }}" wire:navigate variant="outline" size="sm">
                                View Matchup
                            </flux:button>
                            <flux:button href="{{ route('weekly-summary.show', ['league_id' => $league['league_id'] ?? '', 'year' => 2025, 'week' => 1]) }}" wire:navigate variant="primary" size="sm">
                                <flux:icon name="sparkles" class="w-4 h-4 mr-2" />
                                Weekly Summary
                            </flux:button>
                        </div>
                    </div>
                </flux:callout>
            @endforeach
        </div>
    @endif
</section>
