<?php

namespace App\Actions\Matchups;

class ComputeWinProbability
{
    /**
     * Compute win probability using a normal approximation.
     *
     * @param array{mean: float, variance: float} $home
     * @param array{mean: float, variance: float} $away
     * @return array{home: float, away: float}
     */
    public function execute(array $home, array $away): array
    {
        $meanDiff = ($home['mean'] ?? 0.0) - ($away['mean'] ?? 0.0);
        $varSum = max(1e-6, ($home['variance'] ?? 0.0) + ($away['variance'] ?? 0.0));
        $std = sqrt($varSum);

        $z = $meanDiff / $std;
        $homeProb = $this->phi($z);
        $homeProb = max(0.0, min(1.0, $homeProb));

        return [
            'home' => round($homeProb, 4),
            'away' => round(1.0 - $homeProb, 4),
        ];
    }

    /**
     * Standard normal CDF using error function approximation.
     */
    private function phi(float $z): float
    {
        return 0.5 * (1.0 + $this->erf($z / sqrt(2.0)));
    }

    /**
     * Numerical approximation of the error function.
     */
    private function erf(float $x): float
    {
        // Abramowitz and Stegun formula 7.1.26
        $sign = $x < 0 ? -1.0 : 1.0;
        $x = abs($x);

        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;
        $p = 0.3275911;

        $t = 1.0 / (1.0 + $p * $x);
        $y = 1.0 - (((((
            $a5 * $t + $a4) * $t + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$x * $x));

        return $sign * $y;
    }
}
