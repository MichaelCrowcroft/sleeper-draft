<?php

namespace App\Actions\Matchups;

class AggregateTeamTotals
{
    /**
     * @param array<string, array{actual: float, projected: float, used: float, status: string}> $playerPoints
     * @return array{actual: float, projected_remaining: float, total_estimated: float}
     */
    public function execute(array $playerPoints): array
    {
        $actual = 0.0;
        $projectedRemaining = 0.0;

        foreach ($playerPoints as $row) {
            $actual += $row['status'] === 'locked' ? $row['actual'] : 0.0;
            $projectedRemaining += $row['status'] === 'locked' ? 0.0 : $row['projected'];
        }

        $total = $actual + $projectedRemaining;

        return [
            'actual' => round($actual, 2),
            'projected_remaining' => round($projectedRemaining, 2),
            'total_estimated' => round($total, 2),
        ];
    }
}
