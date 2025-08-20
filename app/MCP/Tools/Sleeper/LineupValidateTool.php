<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class LineupValidateTool implements ToolInterface
{
    public function name(): string
    {
        return 'lineup_validate';
    }

    public function description(): string
    {
        return 'Validate a proposed starting lineup against league roster settings and roster eligibility.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['league_id', 'roster_id', 'starters'],
            'properties' => [
                'league_id' => ['type' => 'string'],
                'roster_id' => ['type' => 'integer'],
                'starters' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'minItems' => 1,
                ],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function annotations(): array
    {
        return [];
    }

    private function isEligibleForSlot(string $playerPosition, string $slot): bool
    {
        $slot = strtoupper($slot);
        $playerPosition = strtoupper($playerPosition);

        if ($slot === 'FLEX') {
            return in_array($playerPosition, ['RB', 'WR', 'TE'], true);
        }
        if ($slot === 'SUPER_FLEX' || $slot === 'SUPERFLEX' || $slot === 'SFLEX') {
            return in_array($playerPosition, ['QB', 'RB', 'WR', 'TE'], true);
        }
        if ($slot === 'REC_FLEX') {
            return in_array($playerPosition, ['WR', 'TE'], true);
        }
        if (str_contains($slot, 'WR_RB')) {
            return in_array($playerPosition, ['WR', 'RB'], true);
        }
        if (str_contains($slot, 'WR_TE')) {
            return in_array($playerPosition, ['WR', 'TE'], true);
        }
        if (str_contains($slot, 'RB_TE')) {
            return in_array($playerPosition, ['RB', 'TE'], true);
        }

        // Default exact match
        return $playerPosition === $slot;
    }

    public function execute(array $arguments): mixed
    {
        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $sport = $arguments['sport'] ?? 'nfl';
        $leagueId = (string) $arguments['league_id'];
        $rosterId = (int) $arguments['roster_id'];
        $proposedStarters = array_map('strval', $arguments['starters']);

        $league = $sdk->getLeague($leagueId);
        $rosters = $sdk->getLeagueRosters($leagueId);
        $catalog = $sdk->getPlayersCatalog($sport);

        $roster = collect($rosters)->firstWhere('sleeper_roster_id', (string) $rosterId) ?? [];
        $rosterPlayers = array_map('strval', (array) ($roster['players'] ?? []));

        $errors = [];

        // Validate players belong to roster
        foreach ($proposedStarters as $pid) {
            if (! in_array($pid, $rosterPlayers, true)) {
                $errors[] = "Player {$pid} is not on roster {$rosterId}";
            }
        }

        // Build starting slots from league settings
        $slots = array_values(array_filter((array) ($league['roster_positions'] ?? []), function ($p) {
            $p = strtoupper((string) $p);

            return ! in_array($p, ['BN', 'IR', 'TAXI'], true);
        }));

        if (count($proposedStarters) !== count($slots)) {
            $errors[] = 'Number of starters does not match required slots ('.count($proposedStarters).' vs '.count($slots).')';
        }

        // Map player -> position
        $playerPosition = [];
        foreach ($proposedStarters as $pid) {
            $meta = $catalog[$pid] ?? null;
            $pos = $meta['position'] ?? null;
            if (! $pos) {
                $errors[] = "Missing position for player {$pid}";
            } else {
                $playerPosition[$pid] = strtoupper((string) $pos);
            }
        }

        // Try greedy assignment of players to slots
        $unassigned = $proposedStarters;
        $unfilledSlots = $slots;
        foreach ($slots as $slotIndex => $slot) {
            $matchIndex = null;
            foreach ($unassigned as $idx => $pid) {
                $pos = $playerPosition[$pid] ?? '';
                if ($this->isEligibleForSlot($pos, (string) $slot)) {
                    $matchIndex = $idx;
                    break;
                }
            }
            if ($matchIndex === null) {
                $errors[] = "No eligible player found for slot {$slot}";
            } else {
                unset($unassigned[$matchIndex]);
                unset($unfilledSlots[$slotIndex]);
                $unassigned = array_values($unassigned);
                $unfilledSlots = array_values($unfilledSlots);
            }
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'required_slots' => $slots,
        ];
    }
}
