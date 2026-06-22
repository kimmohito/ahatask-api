<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Project;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class MassiveTaskSeeder extends Seeder
{
    private const TOTAL_TASKS = 1000000;
    private const BATCH_SIZE = 1000;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projectIds = Project::pluck('id')->toArray();
        $userIds = User::pluck('id')->toArray();

        if (empty($projectIds)) {
            throw new \Exception('No projects found. Seed projects first.');
        }

        if (empty($userIds)) {
            throw new \Exception('No users found. Seed users first.');
        }

        $statuses = ['todo', 'doing', 'done'];
        $priorities = ['low', 'medium', 'high'];

        // map project id => slug
        $projectSlugs = Project::pluck('slug', 'id')->toArray();
        $orgSlugs = Organization::pluck('slug', 'id')->toArray();

        for ($i = 0; $i < self::TOTAL_TASKS; $i += self::BATCH_SIZE) {
            $batch = [];

            for ($j = 0; $j < self::BATCH_SIZE; $j++) {
                $projectId = fake()->randomElement($projectIds);
                $orgId = 1;

                // try to pick an assignee from project users if available
                $projectUsers = DB::table('project_user')->where('project_id', $projectId)->pluck('user_id')->toArray();
                if (!empty($projectUsers)) {
                    $assignee = fake()->randomElement($projectUsers);
                } else {
                    $assignee = fake()->randomElement($userIds);
                }

                $batch[] = [
                    'organization_id' => $orgId,
                    'organization_slug' => $orgSlugs[$orgId] ?? null,
                    'project_id' => $projectId,
                    'project_slug' => $projectSlugs[$projectId] ?? null,
                    'slug' => null,
                    'assignee_id' => $assignee,
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
        // Backfill slug for rows inserted via DB::table (bypass Eloquent events)
        // Backfill per-project task_number and slugs for rows inserted via DB::table
        $projects = Project::all();
        foreach ($projects as $project) {
            $tasks = DB::table('tasks')->where('project_id', $project->id)->orderBy('id')->get();
            $n = 1;
            $prefix = $project->project_prefix ?: $project->slug;
            foreach ($tasks as $t) {
                $val = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefix)) . '-' . $n;
                DB::table('tasks')->where('id', $t->id)->update(['task_number' => $n, 'slug' => $val]);
                $n++;
            }
        }
    }
}
