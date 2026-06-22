<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskCommentSeeder extends Seeder
{
    /**
     * Seed realistic comments for tasks so UI timelines look alive.
     */
    public function run(): void
    {
        $tasks = Task::query()
            ->with('project:id,name')
            ->orderByDesc('id')
            ->limit(120)
            ->get();

        if ($tasks->isEmpty()) {
            $this->command?->warn('TaskCommentSeeder skipped: no tasks found');
            return;
        }

        $created = 0;

        foreach ($tasks as $task) {
            $users = User::query()
                ->where('organization_id', $task->organization_id)
                ->inRandomOrder()
                ->limit(3)
                ->get(['id', 'name']);

            if ($users->isEmpty()) {
                continue;
            }

            $projectName = $task->project?->name ?? 'project';
            $author = $users->first();

            $bodies = [
                "[seed] Kickoff: started work on {$task->title} for {$projectName}.",
                "[seed] Update: implementation is in progress, blockers are being tracked.",
                "[seed] QA note: please verify acceptance criteria before moving this task.",
                "[seed] Final note: ready for review after latest changes.",
            ];

            $commentCount = min(count($bodies), max(2, $users->count() + 1));

            for ($i = 0; $i < $commentCount; $i++) {
                $user = $users[$i % $users->count()] ?? $author;

                $comment = TaskComment::firstOrNew([
                    'task_id' => $task->id,
                    'body' => $bodies[$i],
                ]);

                $comment->organization_id = $task->organization_id;
                $comment->user_id = $user?->id;
                $comment->save();

                if ($comment->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        $this->command?->info("TaskCommentSeeder done: {$created} comments created");
    }
}
