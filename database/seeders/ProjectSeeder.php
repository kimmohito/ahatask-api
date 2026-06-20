<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Project;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $org = Organization::first();

        Project::factory()
            ->count(10)
            ->create([
                'organization_id' => $org->id,
            ]);
    }
}
