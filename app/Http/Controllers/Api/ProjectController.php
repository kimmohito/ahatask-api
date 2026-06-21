<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::query()->with('organization');

        $withMembers = filter_var($request->get('with_members', false), FILTER_VALIDATE_BOOLEAN);
        if ($withMembers) {
            $query->with('users');
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->get('q'));
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('slug', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $perPage = (int) $request->get('per_page', 10);
        if ($perPage <= 0) {
            $perPage = 10;
        }

        return $query->latest()->paginate($perPage);
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
        return $project->load(['tasks', 'organization', 'users']);
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
