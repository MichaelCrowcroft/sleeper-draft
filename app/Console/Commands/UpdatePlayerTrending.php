<?php

namespace App\Console\Commands;

use App\Models\Player;
use Illuminate\Console\Command;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;
use MichaelCrowcroft\SleeperLaravel\Requests\Players\GetTrendingPlayers;
use MichaelCrowcroft\SleeperLaravel\Sleeper as SleeperConnector;

class UpdatePlayerTrending extends Command
{
    protected $signature = 'players:update-trending {--lookback=24} {--type=add} {--limit=100}';

    protected $description = 'Update player trending data (adds/drops) for the specified time period';

    public function handle()
    {
        $lookback = (int) $this->option('lookback');
        $type = (string) $this->option('type');
        $limit = (int) $this->option('limit');
        $sport = 'nfl';

        $column = $type === 'add' ? 'adds_24h' : 'drops_24h';

        // Clear existing trending data for this type only
        Player::query()->update([$column => null]);

        $this->info("Fetching {$type} trending data for the last {$lookback} hours...");

        // Use the request directly since the resource method has a bug
        $sleeper = new SleeperConnector();
        $request = new GetTrendingPlayers($sport, $type, $lookback, $limit);
        $response = $sleeper->send($request);

        if (!$response->successful()) {
            $this->error("Failed to fetch trending data: HTTP {$response->status()}");
            return;
        }

        $trendingPlayers = $response->json();

        $this->info("Found " . count($trendingPlayers) . " {$type} trending players");

        $updatedCount = 0;
        foreach ($trendingPlayers as $trendingPlayer) {
            $updated = Player::where('player_id', $trendingPlayer['player_id'])
                ->update([$column => $trendingPlayer['count']]);

            if ($updated > 0) {
                $updatedCount++;
            }
        }

        $this->info("Updated {$updatedCount} players with {$type} trending data");
    }
}
