<?php

namespace App\Actions;

use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class FetchSleeperPlayers
{
    public function execute(string $sport): array
    {
        $response = Sleeper::players()->all($sport);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to fetch players: HTTP '.$response->status());
        }

        $players = $response->json();

        if (! is_array($players)) {
            throw new \RuntimeException('Unexpected response format from Sleeper API.');
        }

        return $players;
    }
}
