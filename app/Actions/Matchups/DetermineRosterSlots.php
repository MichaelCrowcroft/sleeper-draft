<?php

namespace App\Actions\Matchups;

use App\Models\Player;
use Illuminate\Support\Collection;

class DetermineRosterSlots
{
    /**
     * Determine the starter slots that need to be filled.
     * Uses league roster positions if available, otherwise infers from current starters.
     *
     * @param  array<int,string>  $current_starters
     * @param  Collection<string,Player>  $players
     * @param  array<int,string>|null  $roster_positions
     * @return array<int,array{slot: string, eligible_positions: array<int,string>}>
     */
    public function execute(array $current_starters, Collection $players, ?array $roster_positions): array
    {
        if ($roster_positions !== null && $roster_positions !== []) {
            return $this->buildSlotsFromRosterPositions($roster_positions);
        }

        return $this->inferSlotsFromCurrentStarters($current_starters, $players);
    }

    /**
     * Build slots from league roster positions, filtering out bench slots.
     *
     * @param  array<int,string>  $roster_positions
     * @return array<int,array{slot: string, eligible_positions: array<int,string>}>
     */
    private function buildSlotsFromRosterPositions(array $roster_positions): array
    {
        return collect($roster_positions)
            ->reject(fn ($slot) => $this->isBenchSlot((string) $slot))
            ->map(fn ($slot) => [
                'slot' => $slot,
                'eligible_positions' => $this->getEligiblePositions((string) $slot),
            ])
            ->sortBy(fn ($slot) => count($slot['eligible_positions'])) // Most restrictive first
            ->values()
            ->all();
    }

    /**
     * Infer slots from current starters' positions.
     *
     * @param  array<int,string>  $current_starters
     * @param  Collection<string,Player>  $players
     * @return array<int,array{slot: string, eligible_positions: array<int,string>}>
     */
    private function inferSlotsFromCurrentStarters(array $current_starters, Collection $players): array
    {
        return collect($current_starters)
            ->map(function (string $player_id) use ($players) {
                $player = $players->get($player_id);
                $position = $player?->position;

                if (! $position) {
                    return null;
                }

                return [
                    'slot' => $position,
                    'eligible_positions' => [$position],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Check if a slot is bench-like and should be excluded from starters.
     */
    private function isBenchSlot(string $slot): bool
    {
        $normalized = strtoupper(str_replace([' ', '-', '/'], ['_', '_', ''], $slot));

        return in_array($normalized, ['BN', 'BENCH', 'TAXI', 'IR', 'RESERVE'], true);
    }

    /**
     * Get eligible positions for a roster slot (handles FLEX, SUPER_FLEX, etc).
     *
     * @return array<int,string>
     */
    private function getEligiblePositions(string $slot): array
    {
        $normalized = strtoupper(str_replace([' ', '-', '/'], ['_', '_', ''], $slot));

        return match ($normalized) {
            'FLEX', 'WRRBTE' => ['WR', 'RB', 'TE'],
            'REC_FLEX', 'WRTE' => ['WR', 'TE'],
            'RBWR' => ['RB', 'WR'],
            'SUPER_FLEX', 'SF' => ['QB', 'WR', 'RB', 'TE'],
            'WRT', 'WRRB' => ['WR', 'RB', 'TE'],
            default => [$normalized],
        };
    }
}
