<?php

namespace Database\Factories;

use App\Enums\StoryPoints;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->randomElement([
                'Write the method section',
                'Read chapter 7',
                'Make the group slides',
                'Study for the quiz',
                'Email the shift swap',
            ]),
            'status' => TaskStatus::Todo,
            'story_points' => fake()->randomElement(StoryPoints::cases()),
            'due_date' => null,
        ];
    }

    /**
     * Indicate that the deadline has passed.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => today()->subDays(2),
        ]);
    }

    /**
     * Indicate that the Task is done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Done,
        ]);
    }
}
