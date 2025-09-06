<?php

declare(strict_types=1);

use App\Actions\Matchups\AggregateTeamTotals;

it('aggregates totals with locked and upcoming players correctly', function () {
    $agg = new AggregateTeamTotals();
    $rows = [
        'A' => ['actual' => 10.0, 'projected' => 12.0, 'used' => 10.0, 'status' => 'locked'],
        'B' => ['actual' => 0.0, 'projected' => 14.0, 'used' => 14.0, 'status' => 'upcoming'],
        'C' => ['actual' => 7.5, 'projected' => 8.0, 'used' => 7.5, 'status' => 'locked'],
    ];
    $totals = $agg->execute($rows);
    expect($totals['actual'])->toBe(17.5)
        ->and($totals['projected_remaining'])->toBe(14.0)
        ->and($totals['total_estimated'])->toBe(31.5);
});
