<?php

namespace App\MCP\Tools\Draft;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class DraftPickRecommendTool implements ToolInterface
{
    public function name(): string
    {
        return 'draft.pick.recommend';
    }

    public function description(): string
    {
        return 'Recommend best available picks given current pick, roster needs, and board.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['league_id','roster_id','season','week'],
            'properties' => [
                'league_id' => ['type' => 'string'],
                'roster_id' => ['type' => 'integer'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'format' => ['type' => 'string', 'enum' => ['redraft','dynasty','bestball'], 'default' => 'redraft'],
                'limit' => ['type' => 'integer', 'default' => 10],
                'already_drafted' => ['type' => 'array', 'items' => ['type' => 'string']],
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
        $season = (string) $arguments['season'];
        $week = (int) $arguments['week'];
        $format = $arguments['format'] ?? 'redraft';
        $limit = (int) ($arguments['limit'] ?? 10);
        $alreadyDrafted = array_map('strval', (array) ($arguments['already_drafted'] ?? []));

        $league = $sdk->getLeague($leagueId);
        $rosters = $sdk->getLeagueRosters($leagueId);
        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);
        $adp = $sdk->getAdp($season, $format, $sport);

        $myRoster = collect($rosters)->firstWhere('roster_id', $rosterId) ?? [];
        $currentPlayers = array_map('strval', (array) ($myRoster['players'] ?? []));
        $needCounts = ['QB'=>0,'RB'=>0,'WR'=>0,'TE'=>0];
        foreach ($currentPlayers as $pid) {
            $pos = strtoupper((string) (($catalog[$pid]['position'] ?? '') ?: ''));
            if (isset($needCounts[$pos])) {
                $needCounts[$pos]++;
            }
        }

        $adpIndex = [];
        foreach ($adp as $row) {
            $adpIndex[(string) ($row['player_id'] ?? '')] = (float) ($row['adp'] ?? 999.0);
        }

        $candidates = [];
        foreach ($catalog as $pid => $meta) {
            $pid = (string) ($meta['player_id'] ?? $pid);
            if (in_array($pid, $alreadyDrafted, true)) {
                continue;
            }
            $pos = strtoupper((string) ($meta['position'] ?? ''));
            if (! in_array($pos, ['QB','RB','WR','TE'], true)) {
                continue;
            }
            $proj = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
            $adpVal = $adpIndex[$pid] ?? 999.0;
            $needWeight = match ($pos) {
                'RB' => max(0, 3 - ($needCounts['RB'] ?? 0)),
                'WR' => max(0, 4 - ($needCounts['WR'] ?? 0)),
                'QB' => max(0, 1 - ($needCounts['QB'] ?? 0)),
                'TE' => max(0, 1 - ($needCounts['TE'] ?? 0)),
                default => 0,
            };
            $score = $proj + $needWeight * 2.0 + max(0.0, (200.0 - min(200.0, $adpVal))) / 20.0;
            $candidates[] = [
                'player_id' => $pid,
                'name' => $meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')),
                'position' => $pos,
                'team' => $meta['team'] ?? null,
                'adp' => $adpVal,
                'projected_points' => $proj,
                'score' => $score,
                'need_weight' => $needWeight,
            ];
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($candidates, 0, $limit);

        return [ 'recommendations' => $top ];
    }
}
