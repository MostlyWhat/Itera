<?php

namespace Database\Factories;

use App\Models\Sprint;
use App\Models\SprintCommitment;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SprintCommitment>
 */
class SprintCommitmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sprint_id' => Sprint::factory(),
            'task_id' => Task::factory(),
            'task_title' => fn (array $attributes) => $this->task($attributes)->title ?? 'Task',
            'committed_points' => fn (array $attributes) => $this->task($attributes)?->story_points?->value,
        ];
    }

    /**
     * Find the Task of the commitment, to copy its title and points.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function task(array $attributes): ?Task
    {
        return Task::query()->whereKey($attributes['task_id'])->first();
    }
}
