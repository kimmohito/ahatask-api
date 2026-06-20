<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index()
    {
        return Project::with('organization')->latest()->paginate(10);
    }

    public function store(Request $request)
    {
        $project = Project::create([
            'organization_id' => $request->user()->organization_id,
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return $project;
    }

    public function show(Project $project)
    {
        return $project->load('tasks');
    }

    public function update(Request $request, Project $project)
    {
        $project->update($request->only(['name', 'description']));

        return $project;
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return response()->noContent();
    }
}
