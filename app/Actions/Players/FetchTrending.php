<?php

namespace App\Actions\Players;

use App\Models\Player;

class FetchTrending
{
    /**
     * Fetch top trending players by adds or drops over last 24h.
     *
     * @param  string  $type  'add'|'drop'
     * @param  string|null  $position  Optional position filter
     * @param  int  $limit  Number of results to return
     * @return array<int, array{first_name:string|null,last_name:string|null,trend_count:int}>
     */
    public function execute(string $type = 'add', ?string $position = null, int $limit = 5): array
    {
        $column = $type === 'drop' ? 'drops_24h' : 'adds_24h';

        $q = Player::where('active', true)
            ->whereNotNull($column)
            ->where($column, '>', 0);

        if ($position) {
            $q->where('position', strtoupper($position));
        }

        return $q->orderBy($column, 'desc')
            ->limit($limit)
            ->get(['first_name', 'last_name', $column])
            ->map(fn ($p) => [
                'first_name' => $p->first_name,
                'last_name' => $p->last_name,
                'trend_count' => (int) $p->{$column},
            ])->all();
    }
}
