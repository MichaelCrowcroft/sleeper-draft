<?php

declare(strict_types=1);

use App\Actions\Matchups\ComputeWinProbability;

it('returns ~50-50 when means equal and variances equal', function () {
    $svc = new ComputeWinProbability();
    $prob = $svc->execute(['mean' => 100.0, 'variance' => 36.0], ['mean' => 100.0, 'variance' => 36.0]);
    expect($prob['home'])->toBeGreaterThan(0.45)->toBeLessThan(0.55);
});

it('leans to home when home has higher mean', function () {
    $svc = new ComputeWinProbability();
    $prob = $svc->execute(['mean' => 120.0, 'variance' => 36.0], ['mean' => 100.0, 'variance' => 36.0]);
    expect($prob['home'])->toBeGreaterThan(0.70);
});
