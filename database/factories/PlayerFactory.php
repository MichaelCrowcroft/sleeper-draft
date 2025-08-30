<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'player_id' => $this->faker->unique()->bothify('####_####'),
            'sport' => 'nfl',
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'full_name' => $this->faker->name(),
            'search_first_name' => $this->faker->firstName(),
            'search_last_name' => $this->faker->lastName(),
            'search_full_name' => $this->faker->name(),
            'search_rank' => $this->faker->numberBetween(1, 1000),
            'adp' => $this->faker->randomFloat(1, 1, 300),
            'team' => $this->faker->randomElement(['KC', 'BUF', 'SF', 'DAL', 'NE', 'GB']),
            'position' => $this->faker->randomElement(['QB', 'RB', 'WR', 'TE', 'K', 'DEF']),
            'fantasy_positions' => ['QB'],
            'status' => 'Active',
            'active' => true,
            'number' => $this->faker->numberBetween(1, 99),
            'age' => $this->faker->numberBetween(21, 35),
            'years_exp' => $this->faker->numberBetween(0, 15),
            'college' => $this->faker->randomElement(['Alabama', 'Georgia', 'Ohio State', 'Clemson']),
            'birth_date' => $this->faker->date(),
            'birth_city' => $this->faker->city(),
            'birth_state' => $this->faker->state(),
            'birth_country' => 'USA',
            'height' => $this->faker->randomElement(['5\'8"', '5\'10"', '6\'0"', '6\'2"', '6\'4"']),
            'weight' => $this->faker->numberBetween(160, 280),
            'depth_chart_position' => $this->faker->randomElement(['QB', 'RB', 'WR', 'TE']),
            'depth_chart_order' => $this->faker->numberBetween(1, 4),
            'injury_status' => null,
            'injury_body_part' => null,
            'injury_start_date' => null,
            'injury_notes' => null,
            'news_updated' => null,
            'hashtag' => '#'.$this->faker->word(),
            'espn_id' => $this->faker->optional()->bothify('####'),
            'yahoo_id' => $this->faker->optional()->bothify('####'),
            'rotowire_id' => $this->faker->optional()->bothify('####'),
            'pff_id' => $this->faker->optional()->bothify('####'),
            'sportradar_id' => $this->faker->optional()->bothify('########-####-####-####-############'),
            'fantasy_data_id' => $this->faker->optional()->bothify('####'),
            'gsis_id' => $this->faker->optional()->bothify('########-####-####-####-############'),
            'raw' => [],
            'adp_formatted' => null,
            'times_drafted' => $this->faker->numberBetween(0, 100),
            'adp_high' => $this->faker->randomFloat(1, 1, 300),
            'adp_low' => $this->faker->randomFloat(1, 1, 300),
            'adp_stdev' => $this->faker->randomFloat(2, 0, 50),
            'bye_week' => $this->faker->numberBetween(4, 14),
            'adds_24h' => $this->faker->numberBetween(0, 500),
            'drops_24h' => $this->faker->numberBetween(0, 500),
        ];
    }

    /**
     * Indicate that the player is a quarterback.
     */
    public function quarterback(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => 'QB',
            'fantasy_positions' => ['QB'],
        ]);
    }

    /**
     * Indicate that the player is a running back.
     */
    public function runningBack(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => 'RB',
            'fantasy_positions' => ['RB'],
        ]);
    }

    /**
     * Indicate that the player is a wide receiver.
     */
    public function wideReceiver(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => 'WR',
            'fantasy_positions' => ['WR'],
        ]);
    }

    /**
     * Indicate that the player is a tight end.
     */
    public function tightEnd(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => 'TE',
            'fantasy_positions' => ['TE'],
        ]);
    }
}
