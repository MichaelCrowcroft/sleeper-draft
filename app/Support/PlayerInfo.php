<?php

namespace App\Support;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;

class PlayerInfo
{
    /**
     * Return basic player information keyed by player ID.
     *
     * @param  array  $playerIds  Array of player IDs
     * @param  string $sport      Sport key, defaults to nfl
     * @return array<string,array{name:string|null,position:?string,team:?string}>
     */
    public static function fetch(array $playerIds, string $sport = 'nfl'): array
    {
        $playerIds = array_values(array_filter(array_unique(array_map('strval', $playerIds))));
        if (empty($playerIds)) {
            return [];
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $catalog = $sdk->getPlayersCatalog($sport);

        $players = [];
        foreach ($playerIds as $pid) {
            $meta = $catalog[$pid] ?? [];
            $players[$pid] = [
                'name' => $meta['full_name'] ?? trim(($meta['first_name'] ?? '') . ' ' . ($meta['last_name'] ?? '')),
                'position' => $meta['position'] ?? null,
                'team' => $meta['team'] ?? null,
            ];
        }

        return $players;
    }
}

