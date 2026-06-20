<?php

namespace Database\Factories;

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
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['todo', 'doing', 'done']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'assignee_id' => null,
            'project_id' => null,
            'organization_id' => null,
        ];
    }
}
