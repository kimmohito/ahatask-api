<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = Project::all();
        $userCount = User::count();

        if ($projects->isEmpty()) {
            $this->command->info('No projects found, skipping ProjectUserSeeder.');
            return;
        }

        if ($userCount === 0) {
            // create some users if none exist
            User::factory()->count(10)->create();
        }

        $users = User::all();

        foreach ($projects as $project) {
            // assign between 2 and 6 random users to each project
            $assign = $users->random(min( max(2, (int)floor($users->count()/4)), 6));
            $pairs = $assign->mapWithKeys(fn($u) => [$u->id => ['role' => null]])->toArray();
            $project->users()->syncWithoutDetaching($pairs);
        }
    }
}
