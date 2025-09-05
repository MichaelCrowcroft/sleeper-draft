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
        $teams = ['KC', 'BUF', 'SF', 'DAL', 'NE', 'GB'];
        $positions = ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'];

        return [
            'player_id' => \Illuminate\Support\Str::uuid()->toString(),
            'sport' => 'nfl',
            'first_name' => 'Test',
            'last_name' => 'Player',
            'full_name' => 'Test Player',
            'search_first_name' => 'Test',
            'search_last_name' => 'Player',
            'search_full_name' => 'Test Player',
            'search_rank' => random_int(1, 1000),
            'adp' => (float) (random_int(10, 3000) / 10),
            'team' => $teams[array_rand($teams)],
            'position' => $positions[array_rand($positions)],
            'fantasy_positions' => ['QB'],
            'status' => 'Active',
            'active' => true,
            'number' => random_int(1, 99),
            'age' => random_int(21, 35),
            'years_exp' => random_int(0, 15),
            'college' => 'Alabama',
            'birth_date' => date('Y-m-d'),
            'birth_city' => 'City',
            'birth_state' => 'State',
            'birth_country' => 'USA',
            'height' => '6\'0"',
            'weight' => random_int(160, 280),
            'depth_chart_position' => 'QB',
            'depth_chart_order' => random_int(1, 4),
            'injury_status' => null,
            'injury_body_part' => null,
            'injury_start_date' => null,
            'injury_notes' => null,
            'news_updated' => null,
            'hashtag' => '#test',
            'espn_id' => random_int(1000, 99999),
            'yahoo_id' => random_int(1000, 99999),
            'rotowire_id' => random_int(1000, 99999),
            'pff_id' => random_int(1000, 99999),
            'sportradar_id' => \Illuminate\Support\Str::uuid()->toString(),
            'fantasy_data_id' => random_int(1000, 99999),
            'gsis_id' => \Illuminate\Support\Str::uuid()->toString(),
            'raw' => [],
            'adp_formatted' => null,
            'times_drafted' => random_int(0, 100),
            'adp_high' => (float) (random_int(10, 3000) / 10),
            'adp_low' => (float) (random_int(10, 3000) / 10),
            'adp_stdev' => (float) (random_int(0, 5000) / 100),
            'bye_week' => random_int(4, 14),
            'adds_24h' => random_int(0, 500),
            'drops_24h' => random_int(0, 500),
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
