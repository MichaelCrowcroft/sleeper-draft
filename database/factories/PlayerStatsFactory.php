<?php

namespace Database\Factories;

use App\Models\PlayerStats;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerStats>
 */
class PlayerStatsFactory extends Factory
{
    protected $model = PlayerStats::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $season = (int) $this->faker->numberBetween(2018, 2025);
        $week = (int) $this->faker->numberBetween(1, 18);

        return [
            'player_id' => \Illuminate\Support\Str::uuid()->toString(),
            'game_date' => $this->faker->dateTimeBetween("$season-09-01", "$season-12-31"),
            'season' => $season,
            'week' => $week,
            'season_type' => 'regular',
            'sport' => 'nfl',
            'company' => 'sleeper',
            'team' => $this->faker->randomElement(['KC', 'BUF', 'SF', 'DAL', 'NE', 'GB']),
            'opponent' => $this->faker->randomElement(['KC', 'BUF', 'SF', 'DAL', 'NE', 'GB']),
            'game_id' => $this->faker->uuid(),
            'updated_at_ms' => $this->faker->numberBetween(1_700_000_000_000, 1_800_000_000_000),
            'last_modified_ms' => $this->faker->numberBetween(1_700_000_000_000, 1_800_000_000_000),
            'stats' => [
                'pts_half_ppr' => $this->faker->randomFloat(1, 0, 40),
                'pts_ppr' => $this->faker->randomFloat(1, 0, 40),
                'rec' => $this->faker->numberBetween(0, 12),
                'rec_yd' => $this->faker->numberBetween(0, 200),
                'rush_yd' => $this->faker->numberBetween(0, 200),
                'rush_td' => $this->faker->numberBetween(0, 3),
                'pass_yd' => $this->faker->numberBetween(0, 450),
                'pass_td' => $this->faker->numberBetween(0, 5),
                'int' => $this->faker->numberBetween(0, 3),
            ],
            'raw' => [],
        ];
    }
}
