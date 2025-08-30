<?php

namespace App\Console\Commands;

use App\Models\Player;
use Illuminate\Console\Command;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

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

        Player::query()->update([$column => null]);

        $trendingPlayers = Sleeper::players()
            ->trending($sport, $lookback, $limit, $type)
            ->json();

        foreach ($trendingPlayers as $trendingPlayer) {
            Player::where('player_id', $trendingPlayer['player_id'])
                ->update([$column => $trendingPlayer['count']]);
        }
    }
}
