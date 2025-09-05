<?php

namespace Database\Factories;

use App\Models\PlayerProjections;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerProjections>
 */
class PlayerProjectionsFactory extends Factory
{
    protected $model = PlayerProjections::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $season = (int) $this->faker->numberBetween(2024, 2025);
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
            // Use flattened PPR fields to match SQLite schema
            'pts_ppr' => $this->faker->randomFloat(1, 0, 40),
            'gms_active' => 1,
        ];
    }
}
