<?php

namespace App\MCP\Tools\Draft;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class DraftBoardBuildTool implements ToolInterface
{
    public function name(): string
    {
        return 'draft_board_build';
    }

    public function description(): string
    {
        return 'Build a draft board from ADP + projections with simple positional tiers.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['season','week'],
            'properties' => [
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
                'format' => ['type' => 'string', 'enum' => ['redraft','dynasty','bestball'], 'default' => 'redraft'],
                'tier_gaps' => ['type' => 'number', 'default' => 10.0],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'default' => 300],
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
        $season = (string) $arguments['season'];
        $week = (int) $arguments['week'];
        $format = $arguments['format'] ?? 'redraft';
        $limit = (int) ($arguments['limit'] ?? 300);
        $tierGap = (float) ($arguments['tier_gaps'] ?? 10.0);

        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);
        $adp = $sdk->getAdp($season, $format, $sport);

        $adpIndex = [];
        foreach ($adp as $row) {
            $adpIndex[(string) ($row['player_id'] ?? '')] = (float) ($row['adp'] ?? 999.0);
        }

        $rows = [];
        foreach ($catalog as $pid => $meta) {
            $pid = (string) ($meta['player_id'] ?? $pid);
            $pos = $meta['position'] ?? null;
            if (! $pos || $pos === 'DEF' || $pos === 'K') {
                continue;
            }
            $proj = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
            $adpVal = $adpIndex[$pid] ?? 999.0;
            $score = $proj + max(0.0, (200.0 - min(200.0, $adpVal))) / 20.0;

            $rows[] = [
                'player_id' => $pid,
                'name' => $meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')),
                'position' => $pos,
                'team' => $meta['team'] ?? null,
                'adp' => $adpVal,
                'projected_points' => $proj,
                'score' => $score,
            ];
        }

        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);
        $rows = array_slice($rows, 0, $limit);

        // Build tiers per position using score gaps
        $tiers = [];
        foreach (['QB','RB','WR','TE'] as $pos) {
            $posRows = array_values(array_filter($rows, fn ($r) => ($r['position'] ?? null) === $pos));
            $tier = 1;
            $last = null;
            foreach ($posRows as $idx => $r) {
                if ($last !== null && ($last - $r['score']) >= $tierGap) {
                    $tier++;
                    $last = $r['score'];
                }
                if ($last === null) {
                    $last = $r['score'];
                }
                $r['tier'] = $tier;
                $tiers[$pos][] = $r;
            }
        }

        return [
            'board' => $rows,
            'tiers' => $tiers,
        ];
    }
}
