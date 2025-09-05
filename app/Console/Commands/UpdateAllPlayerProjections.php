<?php

namespace App\Console\Commands;

use App\Jobs\UpdatePlayerProjectionsJob;
use App\Models\Player;
use Illuminate\Console\Command;

class UpdateAllPlayerProjections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sleeper:players:projections:update-all
                           {--season=2025 : The season year (default: current year 2024)}
                           {--season-type=regular : Season type (regular, postseason)}
                           ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch jobs to update projections for all players with rate limiting (max 250/min)';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $season = $this->option('season');
        $seasonType = $this->option('season-type');

        $players = Player::query()
            ->whereNotNull('player_id')
            ->where('active', true)
            ->where(function ($q) {
                $q->whereJsonContains('fantasy_positions', 'QB')
                    ->orWhereJsonContains('fantasy_positions', 'RB')
                    ->orWhereJsonContains('fantasy_positions', 'WR')
                    ->orWhereJsonContains('fantasy_positions', 'TE')
                    ->orWhereJsonContains('fantasy_positions', 'K')
                    ->orWhereJsonContains('fantasy_positions', 'DEF');
            })
            ->get();

        foreach ($players as $player) {
            UpdatePlayerProjectionsJob::dispatch(
                $player->player_id,
                $season,
                $seasonType,
            )->onQueue('default');
        }
    }
}
