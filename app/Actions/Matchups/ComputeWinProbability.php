<?php

namespace App\Actions\Matchups;

class ComputeWinProbability
{
    public function execute(array $home, array $away): array
    {
        $home_mean = ($home['mean'] ?? 0.0);
        $away_mean = ($away['mean'] ?? 0.0);
        $home_variance = max(0.0, ($home['variance'] ?? 0.0));
        $away_variance = max(0.0, ($away['variance'] ?? 0.0));

        $mean_diff = $home_mean - $away_mean;
        $variance_sum = $home_variance + $away_variance;

        if($variance_sum <= 0.0) {
            if($mean_diff > 0.0) {
                return ['home' => 1.0, 'away' => 0.0];
            }
            if($mean_diff < 0.0) {
                return ['home' => 0.0, 'away' => 1.0];
            }
            return ['home' => 0.5, 'away' => 0.5];
        }

        $std = sqrt($variance_sum);
        $z = $mean_diff / $std;

        $home_probability = 0.5 * (1.0 + $this->erf($z / sqrt(2.0)));
        $home_probability = max(0.0, min(1.0, $home_probability));

        return [
            'home' => $home_probability,
            'away' => 1.0 - $home_probability,
        ];
    }

    private function erf(float $x): float
    {
        $sign = $x < 0 ? -1.0 : 1.0;
        $x = abs($x);

        // Abramowitz and Stegun approximation
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
