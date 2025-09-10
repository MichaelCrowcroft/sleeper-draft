<?php

namespace App\Actions\Players;

use App\Actions\Matchups\DetermineCurrentWeek;
use App\Actions\Sleeper\BuildPlayerLeagueTeamMap;
use App\Http\Resources\PlayerResource;
use App\Models\Player;

class FetchPlayersSeasonDataPage
{
    public function __construct(
        public DetermineCurrentWeek $determineCurrentWeek,
        public BuildPlayerLeagueTeamMap $buildPlayerLeagueTeamMap,
    ) {}

    /**
     * Fetch a page of players with eager-loaded season stats and projections.
     * Returns resource arrays and nextCursor.
     *
     * @return array{players: array<int, array>, count:int, hasMore:bool, nextCursor:?string, limit:int, offset:int, upcomingWeek:int}
     */
    public function execute(?string $position, int $limit, int $offset, ?string $leagueId = null): array
    {
        // Enforce limits
        $limit = max(1, min(1000, $limit));
        $offset = max(0, $offset);

        $state = $this->determineCurrentWeek->execute('nfl');
        $upcomingWeek = (int) (($state['week'] ?? 1) + 1);

        $query = Player::query();
        if ($position) {
            $query->where('position', strtoupper($position));
        }

        $query->with([
            'stats2024',
            'projections2025',
            'projections' => function ($q) use ($upcomingWeek) {
                $q->where('season', 2025)->where('week', $upcomingWeek);
            },
        ]);

        $rows = $query->orderBy('search_rank')
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $players = $hasMore ? $rows->slice(0, $limit)->values() : $rows;

        $data = $players->map(fn ($p) => (new PlayerResource($p))->resolve())->all();

        if (is_string($leagueId) && $leagueId !== '') {
            $map = $this->buildPlayerLeagueTeamMap->execute($leagueId);
            foreach ($data as &$item) {
                $pid = $item['player_id'] ?? null;
                $item['league_team_name'] = ($pid !== null && isset($map[(string) $pid]))
                    ? $map[(string) $pid]
                    : 'Free Agent';
            }
            unset($item);
        }

        $nextCursor = null;
        if ($hasMore) {
            $nextCursor = base64_encode(json_encode([
                'offset' => $offset + $limit,
                'limit' => $limit,
                'position' => $position,
                'league_id' => $leagueId,
            ]));
        }

        return [
            'players' => $data,
            'count' => count($data),
            'hasMore' => $hasMore,
            'nextCursor' => $nextCursor,
            'limit' => $limit,
            'offset' => $offset,
            'upcomingWeek' => $upcomingWeek,
        ];
    }
}
