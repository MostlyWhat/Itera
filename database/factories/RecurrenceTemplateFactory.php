<?php

namespace Database\Factories;

use App\Enums\StoryPoints;
use App\Models\Project;
use App\Models\RecurrenceTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurrenceTemplate>
 */
class RecurrenceTemplateFactory extends Factory
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
                'Do the weekly quiz',
                'Read the lecture notes',
                'Go to the study group',
                'Plan the shifts',
            ]),
            'description' => null,
            'weekday' => 1,
            'story_points' => StoryPoints::Two,
        ];
    }
}
