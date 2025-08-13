<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class WaiverRecommendationsTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false; // Using HTTP; could be streamed later if needed
    }

    public function name(): string
    {
        return 'waiver_recommendations';
    }

    public function description(): string
    {
        return 'Recommend waiver pickups for a roster with simple heuristic using trending + projections.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['league_id', 'roster_id', 'season', 'week'],
            'properties' => [
                'league_id' => ['type' => 'string'],
                'roster_id' => ['type' => 'integer'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'max_candidates' => ['type' => 'integer', 'minimum' => 1, 'default' => 10],
            ],
            'additionalProperties' => false,
        ];
    }

    public function annotations(): array
    {
        return [];
    }

    public function execute(array $arguments): mixed
    {
        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);

        $sport = $arguments['sport'] ?? 'nfl';
        $leagueId = (string) $arguments['league_id'];
        $rosterId = (int) $arguments['roster_id'];
        $state = $sdk->getState($sport);
        $season = (string) ($arguments['season'] ?? ($state['season'] ?? date('Y')));
        $week = (int) ($arguments['week'] ?? (int) ($state['week'] ?? 1));
        $max = (int) ($arguments['max_candidates'] ?? 10);

        $league = $sdk->getLeague($leagueId);
        $rosters = $sdk->getLeagueRosters($leagueId);
        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);

        $roster = collect($rosters)->firstWhere('roster_id', $rosterId) ?? [];
        $owned = array_map('strval', (array) ($roster['players'] ?? []));

        $trendingAdds = $sdk->getPlayersTrending('add', $sport, 48, 100);

        $candidates = [];
        foreach ($trendingAdds as $entry) {
            $pid = (string) ($entry['player_id'] ?? '');
            if ($pid === '' || in_array($pid, $owned, true)) {
                continue;
            }
            $proj = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
            $meta = $catalog[$pid] ?? [];
            $pos = $meta['position'] ?? null;
            $team = $meta['team'] ?? null;
            $score = $proj + (float) ($entry['count'] ?? 0) / 10000.0; // light heuristic: projections + scaled trend
            $candidates[] = [
                'player_id' => $pid,
                'projected_points' => $proj,
                'trend_count' => (int) ($entry['count'] ?? 0),
                'score' => $score,
                'position' => $pos,
                'team' => $team,
            ];
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        $candidates = array_slice($candidates, 0, $max);

        return ['picks' => $candidates];
    }
}
