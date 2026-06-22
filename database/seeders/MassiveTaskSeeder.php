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
    private const TOTAL_TASKS = 100000;
    private const BATCH_SIZE = 1000;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = Project::query()
            ->select(['id', 'organization_id', 'slug', 'project_prefix'])
            ->get();
        $projectIds = $projects->pluck('id')->all();
        $userIds = User::pluck('id')->toArray();

        if (empty($projectIds)) {
            throw new \Exception('No projects found. Seed projects first.');
        }

        if (empty($userIds)) {
            throw new \Exception('No users found. Seed users first.');
        }

        $statuses = ['todo', 'doing', 'done'];
        $priorities = ['low', 'medium', 'high'];

        $projectSlugs = $projects->pluck('slug', 'id')->all();
        $projectPrefixes = $projects->pluck('project_prefix', 'id')->all();
        $projectOrgIds = $projects->pluck('organization_id', 'id')->all();

        $organizationSlugs = Organization::pluck('slug', 'id')->all();

        $projectUsers = DB::table('project_user')
            ->select(['project_id', 'user_id'])
            ->get()
            ->groupBy('project_id')
            ->map(fn ($rows) => $rows->pluck('user_id')->all())
            ->all();

        $projectCounters = DB::table('tasks')
            ->select('project_id', DB::raw('COALESCE(MAX(task_number), 0) as max_task_number'))
            ->groupBy('project_id')
            ->pluck('max_task_number', 'project_id')
            ->all();

        for ($i = 0; $i < self::TOTAL_TASKS; $i += self::BATCH_SIZE) {
            $batch = [];

            for ($j = 0; $j < self::BATCH_SIZE; $j++) {
                $projectId = fake()->randomElement($projectIds);
                $orgId = $projectOrgIds[$projectId] ?? null;

                // try to pick an assignee from project users if available
                $projectUserIds = $projectUsers[$projectId] ?? [];
                if (!empty($projectUserIds)) {
                    $assignee = fake()->randomElement($projectUserIds);
                } else {
                    $assignee = fake()->randomElement($userIds);
                }

                $taskNumber = ($projectCounters[$projectId] ?? 0) + 1;
                $projectCounters[$projectId] = $taskNumber;

                $prefix = $projectPrefixes[$projectId] ?: $projectSlugs[$projectId] ?? '';
                $slugPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $prefix));

                $batch[] = [
                    'organization_id' => $orgId,
                    'organization_slug' => $organizationSlugs[$orgId] ?? null,
                    'project_id' => $projectId,
                    'project_slug' => $projectSlugs[$projectId] ?? null,
                    'task_number' => $taskNumber,
                    'slug' => $slugPrefix !== '' ? $slugPrefix . '-' . $taskNumber : null,
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
    }
}
