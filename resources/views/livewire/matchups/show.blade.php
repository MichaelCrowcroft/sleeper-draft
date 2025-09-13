<?php

use App\Actions\Matchups\AssembleMatchupViewModel;
use App\Actions\Matchups\EnrichMatchupsWithPlayerData;
use App\Actions\Matchups\FilterMatchups;
use App\Actions\Matchups\GetMatchupsWithOwners;
use App\Actions\Sleeper\GetSeasonState;
use App\Actions\Sleeper\FetchLeague;
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
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-4">
        <div>
            <flux:heading size="xl">Matchups</flux:heading>
            <p class="text-muted-foreground">Week {{ $this->week }} • {{ $this->league['name'] ?? 'League' }}</p>
        </div>
    </div>

    <pre class="text-xs">{{ json_encode($this->matchup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
</section>
