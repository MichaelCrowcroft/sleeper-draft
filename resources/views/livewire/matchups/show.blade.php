<?php

use App\Actions\Matchups\AssembleMatchupViewModel;
use App\Actions\Sleeper\GetSeasonState;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

new class extends Component
{
    public int|string $league_id;
    public ?int $week = null;

    public function mount(int $league_id, ?int $week = null): void
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
        $matchups = Sleeper::leagues()->matchups($this->league_id, $this->week)->json();
        if($matchups === []) {
            return [];
        }

        return $matchups;
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-4">
        <div>
            <flux:heading size="xl">Matchup</flux:heading>
            <p class="text-muted-foreground">Week {{ $this->model['week'] }} • {{ $this->model['league']['name'] ?? 'League' }}</p>
        </div>
    </div>

    <p>{!! $this->matchup !!}/p>
</section>
