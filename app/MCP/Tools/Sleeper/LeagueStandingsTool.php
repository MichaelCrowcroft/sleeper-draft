<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class LeagueStandingsTool implements ToolInterface
{
    public function name(): string
    {
        return 'league_standings';
    }

    public function description(): string
    {
        return 'Compute league standings from roster records and points.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['league_id'],
            'properties' => [
                'league_id' => ['type' => 'string'],
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

        $rosters = $sdk->getLeagueRosters($arguments['league_id']);

        $standings = [];
        foreach ($rosters as $roster) {
            $settings = $roster['settings'] ?? [];
            $wins = (int) ($settings['wins'] ?? 0);
            $losses = (int) ($settings['losses'] ?? 0);
            $ties = (int) ($settings['ties'] ?? 0);
            $pf = (float) ($settings['fpts'] ?? 0) + (float) ($settings['fpts_decimal'] ?? 0) / 100;
            $pa = (float) ($settings['fpts_against'] ?? 0) + (float) ($settings['fpts_against_decimal'] ?? 0) / 100;

            $standings[] = [
                'roster_id' => $roster['roster_id'] ?? null,
                'owner_id' => $roster['owner_id'] ?? ($roster['owner_id'] ?? null),
                'wins' => $wins,
                'losses' => $losses,
                'ties' => $ties,
                'points_for' => $pf,
                'points_against' => $pa,
            ];
        }

        usort($standings, function ($a, $b) {
            // Sort by wins desc, then points_for desc, then points_against asc
            return [$b['wins'], $b['points_for'], -$b['points_against']] <=> [$a['wins'], $a['points_for'], -$a['points_against']];
        });

        foreach ($standings as $index => &$row) {
            $row['rank'] = $index + 1;
        }

        return ['standings' => $standings];
    }
}
