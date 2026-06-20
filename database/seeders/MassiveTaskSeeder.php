<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class MassiveTaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    $projectIds = Project::pluck('id')->toArray();
    $userIds = User::pluck('id')->toArray();

    if (empty($projectIds)) {
        throw new \Exception("No projects found. Seed projects first.");
    }

    if (empty($userIds)) {
        throw new \Exception("No users found. Seed users first.");
    }

    $statuses = ['todo', 'doing', 'done'];
    $priorities = ['low', 'medium', 'high'];

    for ($i = 0; $i < 1000000; $i += 1000) {
        $batch = [];

        for ($j = 0; $j < 1000; $j++) {
            $batch[] = [
                'organization_id' => 1,
                'project_id' => fake()->randomElement($projectIds), // ✅ FIX
                'assignee_id' => fake()->randomElement($userIds),
                'title' => fake()->sentence(),
                'description' => fake()->paragraph(),
                'status' => fake()->randomElement($statuses),
                'priority' => fake()->randomElement($priorities),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('tasks')->insert($batch);
    }
}
}
