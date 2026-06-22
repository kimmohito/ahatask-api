<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CrossOrganizationAccessSeeder extends Seeder
{
    /**
     * Seed two isolated org scenarios so API auth/scoping can be verified.
     */
    public function run(): void
    {
        $orgA = Organization::firstOrCreate(['name' => 'QA Org A']);
        $orgB = Organization::firstOrCreate(['name' => 'QA Org B']);

        $userA = User::firstOrNew(['email' => 'qa-org-a@example.com']);
        $userA->name = 'QA User Org A';
        $userA->password = Hash::make('password');
        $userA->organization_id = $orgA->id;
        $userA->save();

        $userB = User::firstOrNew(['email' => 'qa-org-b@example.com']);
        $userB->name = 'QA User Org B';
        $userB->password = Hash::make('password');
        $userB->organization_id = $orgB->id;
        $userB->save();

        if (method_exists($userA, 'assignRole')) {
            $userA->assignRole('member');
            $userB->assignRole('member');
        }

        $projectA = Project::firstOrCreate(
            ['name' => 'QA Isolation Project A', 'organization_id' => $orgA->id],
            ['description' => 'Used to verify org isolation for org A']
        );

        $projectB = Project::firstOrCreate(
            ['name' => 'QA Isolation Project B', 'organization_id' => $orgB->id],
            ['description' => 'Used to verify org isolation for org B']
        );

        $projectA->users()->syncWithoutDetaching([$userA->id => ['role' => 'member']]);
        $projectB->users()->syncWithoutDetaching([$userB->id => ['role' => 'member']]);

        $this->seedTaskSet($orgA->id, $projectA->id, $userA->id, 'Org A');
        $this->seedTaskSet($orgB->id, $projectB->id, $userB->id, 'Org B');

        $this->command?->info('CrossOrganizationAccessSeeder ready');
        $this->command?->info('User A: qa-org-a@example.com / password');
        $this->command?->info('User B: qa-org-b@example.com / password');
    }

    private function seedTaskSet(int $organizationId, int $projectId, int $assigneeId, string $tag): void
    {
        $tasks = [
            ['title' => "{$tag} Task Todo", 'status' => 'todo', 'priority' => 'high'],
            ['title' => "{$tag} Task Doing", 'status' => 'doing', 'priority' => 'medium'],
            ['title' => "{$tag} Task Done", 'status' => 'done', 'priority' => 'low'],
        ];

        foreach ($tasks as $task) {
            Task::firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'project_id' => $projectId,
                    'title' => $task['title'],
                ],
                [
                    'description' => 'Seeded for cross organization access verification',
                    'status' => $task['status'],
                    'priority' => $task['priority'],
                    'assignee_id' => $assigneeId,
                ]
            );
        }
    }
}
