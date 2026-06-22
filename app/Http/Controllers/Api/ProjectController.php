<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OAAttr;

/**
 * @OA\Tag(name="Projects", description="Project management")
 */

class ProjectController extends Controller
{
    #[OAAttr\Get(
        path: '/api/projects',
        tags: ['Projects'],
        summary: 'List projects',
        parameters: [
            new OAAttr\Parameter(name: 'with_members', in: 'query', required: false, schema: new OAAttr\Schema(type: 'boolean')),
        ],
        responses: [new OAAttr\Response(response: 200, description: 'Projects list')]
    )]
    /**
     * @OA\Get(
     *     path="/api/projects",
     *     tags={"Projects"},
     *     summary="List projects",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="with_members", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Projects list")
     * )
     */
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

    #[OAAttr\Post(
        path: '/api/projects',
        tags: ['Projects'],
        summary: 'Create a project',
        requestBody: new OAAttr\RequestBody(
            required: true,
            content: new OAAttr\JsonContent(
                required: ['name'],
                properties: [
                    new OAAttr\Property(property: 'name', type: 'string', example: 'New Project'),
                    new OAAttr\Property(property: 'description', type: 'string', example: 'Project description'),
                ]
            )
        ),
        responses: [new OAAttr\Response(response: 200, description: 'Project created')]
    )]
    /**
     * @OA\Post(
     *     path="/api/projects",
     *     tags={"Projects"},
     *     summary="Create a project",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name"}, @OA\Property(property="name", type="string", example="New Project"), @OA\Property(property="description", type="string", example="Project description"))),
     *     @OA\Response(response=200, description="Project created")
     * )
     */
    public function store(Request $request)
    {
        $project = Project::create([
            'organization_id' => $request->user()->organization_id,
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return $project;
    }

    #[OAAttr\Get(
        path: '/api/projects/{project}',
        tags: ['Projects'],
        summary: 'Get a project',
        parameters: [new OAAttr\Parameter(name: 'project', in: 'path', required: true, schema: new OAAttr\Schema(type: 'string'))],
        responses: [new OAAttr\Response(response: 200, description: 'Project detail')]
    )]
    /**
     * @OA\Get(
     *     path="/api/projects/{project}",
     *     tags={"Projects"},
     *     summary="Get a project",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Project detail")
     * )
     */
    public function show(Project $project)
    {
        return $project->load(['tasks', 'organization', 'users']);
    }

    #[OAAttr\Put(
        path: '/api/projects/{project}',
        tags: ['Projects'],
        summary: 'Update a project',
        parameters: [new OAAttr\Parameter(name: 'project', in: 'path', required: true, schema: new OAAttr\Schema(type: 'string'))],
        requestBody: new OAAttr\RequestBody(
            required: true,
            content: new OAAttr\JsonContent(
                properties: [
                    new OAAttr\Property(property: 'name', type: 'string', example: 'Updated Project'),
                    new OAAttr\Property(property: 'description', type: 'string', example: 'Updated description'),
                ]
            )
        ),
        responses: [new OAAttr\Response(response: 200, description: 'Project updated')]
    )]
    /**
     * @OA\Put(
     *     path="/api/projects/{project}",
     *     tags={"Projects"},
     *     summary="Update a project",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="name", type="string", example="Updated Project"), @OA\Property(property="description", type="string", example="Updated description"))),
     *     @OA\Response(response=200, description="Project updated")
     * )
     */
    public function update(Request $request, Project $project)
    {
        $project->update($request->only(['name', 'description']));

        return $project;
    }

    #[OAAttr\Delete(
        path: '/api/projects/{project}',
        tags: ['Projects'],
        summary: 'Delete a project',
        parameters: [new OAAttr\Parameter(name: 'project', in: 'path', required: true, schema: new OAAttr\Schema(type: 'string'))],
        responses: [new OAAttr\Response(response: 204, description: 'Project deleted')]
    )]
    /**
     * @OA\Delete(
     *     path="/api/projects/{project}",
     *     tags={"Projects"},
     *     summary="Delete a project",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Project deleted")
     * )
     */
    public function destroy(Project $project)
    {
        $project->delete();

        return response()->noContent();
    }
}
