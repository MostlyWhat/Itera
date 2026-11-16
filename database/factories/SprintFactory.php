<?php

namespace Database\Factories;

use App\Models\Sprint;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sprint>
 */
class SprintFactory extends Factory
{
    /**
     * Define the model's default state: the current week.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'week_start' => today()->startOfWeek(),
            'goal' => 'Hand in the lab report',
            'target_points' => 13,
        ];
    }

    /**
     * Indicate that the Sprint is the week after the current one.
     */
    public function nextWeek(): static
    {
        return $this->state(fn (array $attributes) => [
            'week_start' => today()->startOfWeek()->addWeek(),
        ]);
    }

    /**
     * Indicate that the Sprint was the week before the current one.
     */
    public function lastWeek(): static
    {
        return $this->state(fn (array $attributes) => [
            'week_start' => today()->startOfWeek()->subWeek(),
        ]);
    }
}
