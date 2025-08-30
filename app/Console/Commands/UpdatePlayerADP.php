<?php

namespace App\Console\Commands;

use App\Models\Player;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdatePlayerADP extends Command
{
    protected $signature = 'adp:update {--teams=12} {--year=2025}';

    protected $description = 'Fetch ADP data from Fantasy Football Calculator API and update players table';

    public function handle()
    {
        $teams = $this->option('teams');
        $year = $this->option('year');

        $url = "https://fantasyfootballcalculator.com/api/v1/adp/ppr?teams={$teams}&year={$year}&position=all";

        $response = Http::timeout(30)->get($url);

        $data = $response->json();

        $players = $data['players'];

        foreach ($players as $player) {
            $updated = $this->updatePlayerAdp($player);
        }
    }

    /**
     * Update a player's ADP data
     */
    private function updatePlayerAdp(array $playerData)
    {
        $player = null;

        if (isset($playerData['name'])) {
            $player = Player::where('full_name', $playerData['name'])->first();
        }

        // If not found, try to match by first and last name
        if (! $player && isset($playerData['name'])) {
            $nameParts = explode(' ', $playerData['name'], 2);
            if (count($nameParts) === 2) {
                [$firstName, $lastName] = $nameParts;
                $player = Player::where('first_name', $firstName)
                    ->where('last_name', $lastName)
                    ->first();
            }
        }

        // If still not found, try searching by last name and position
        if (! $player && isset($playerData['name']) && isset($playerData['position'])) {
            $nameParts = explode(' ', $playerData['name'], 2);
            if (count($nameParts) >= 1) {
                $lastName = end($nameParts);
                $player = Player::where('last_name', $lastName)
                    ->where('position', $playerData['position'])
                    ->first();
            }
        }

        if (! $player) {
            return false;
        }

        // Update ADP data
        $player->update(array_filter([
            'adp' => isset($playerData['adp']) ? (float) $playerData['adp'] : null,
            'adp_formatted' => $playerData['adp_formatted'] ?? null,
            'times_drafted' => isset($playerData['times_drafted']) ? (int) $playerData['times_drafted'] : null,
            'adp_high' => isset($playerData['high']) ? (float) $playerData['high'] : null,
            'adp_low' => isset($playerData['low']) ? (float) $playerData['low'] : null,
            'adp_stdev' => isset($playerData['stdev']) ? (float) $playerData['stdev'] : null,
            'bye_week' => isset($playerData['bye']) ? (int) $playerData['bye'] : null,
        ], fn ($value) => $value !== null));
    }
}
