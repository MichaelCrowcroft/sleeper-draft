<?php

namespace App\MCP\Tools\Roster;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class RosterNeedsTool implements ToolInterface
{
    public function name(): string
    {
        return 'roster_needs';
    }

    public function description(): string
    {
        return 'Compute roster needs based on league starting slots and current roster composition.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['league_id', 'roster_id'],
            'properties' => [
                'league_id' => ['type' => 'string'],
                'roster_id' => ['type' => 'integer'],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
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

        $league = $sdk->getLeague($leagueId);
        $rosters = $sdk->getLeagueRosters($leagueId);
        $catalog = $sdk->getPlayersCatalog($sport);

        $myRoster = collect($rosters)->firstWhere('sleeper_roster_id', (string) $rosterId) ?? [];
        $players = array_map('strval', (array) ($myRoster['players'] ?? []));

        $starterSlots = array_values(array_filter((array) ($league['roster_positions'] ?? []), function ($p) {
            $p = strtoupper((string) $p);

            return ! in_array($p, ['BN', 'IR', 'TAXI'], true);
        }));

        $counts = ['QB' => 0, 'RB' => 0, 'WR' => 0, 'TE' => 0];
        foreach ($players as $pid) {
            $pos = strtoupper((string) (($catalog[$pid]['position'] ?? '') ?: ''));
            if (isset($counts[$pos])) {
                $counts[$pos]++;
            }
        }

        // approximate required counts by counting hard slots; FLEX counted separately
        $required = ['QB' => 0, 'RB' => 0, 'WR' => 0, 'TE' => 0, 'FLEX' => 0];
        foreach ($starterSlots as $slot) {
            $slot = strtoupper((string) $slot);
            if (isset($required[$slot])) {
                $required[$slot]++;
            } elseif (in_array($slot, ['SUPER_FLEX', 'SUPERFLEX', 'SFLEX'])) {
                $required['FLEX']++;
                $required['QB']++;
            } elseif (in_array($slot, ['FLEX', 'REC_FLEX', 'WR_RB', 'WR_TE', 'RB_TE'])) {
                $required['FLEX']++;
            } elseif (in_array($slot, ['WR', 'RB', 'QB', 'TE'])) {
                $required[$slot]++;
            }
        }

        $needs = [
            'QB' => max(0, ($required['QB'] ?? 0) - ($counts['QB'] ?? 0)),
            'RB' => max(0, ($required['RB'] ?? 0) - ($counts['RB'] ?? 0)),
            'WR' => max(0, ($required['WR'] ?? 0) - ($counts['WR'] ?? 0)),
            'TE' => max(0, ($required['TE'] ?? 0) - ($counts['TE'] ?? 0)),
            'FLEX' => ($required['FLEX'] ?? 0),
        ];

        return [
            'counts' => $counts,
            'required' => $required,
            'needs' => $needs,
        ];
    }
}
