<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::query()
            ->with(['project', 'assignee']);

        // FILTERS
        $query->when($request->status, fn($q) =>
            $q->where('status', $request->status)
        );

        $query->when($request->priority, fn($q) =>
            $q->where('priority', $request->priority)
        );

        $query->when($request->assignee_id, fn($q) =>
            $q->where('assignee_id', $request->assignee_id)
        );

        $query->when($request->project_id, fn($q) =>
            $q->where('project_id', $request->project_id)
        );

        // DATE RANGE
        $query->when($request->from, fn($q) =>
            $q->whereDate('created_at', '>=', $request->from)
        );

        $query->when($request->to, fn($q) =>
            $q->whereDate('created_at', '<=', $request->to)
        );

        // SORTING
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');

        $query->orderBy($sortBy, $sortDir);

        // PAGINATION SIZE (VERY IMPORTANT)
        $perPage = (int) $request->get('per_page', 10);

        if (!in_array($perPage, [5,10,20,50,100,1000])) {
            $perPage = 10;
        }

        return response()->json(
            $query->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $task = Task::create([
            'organization_id' => $request->user()->organization_id,
            'project_id' => $request->project_id,
            'title' => $request->title,
            'description' => $request->description,
            'status' => $request->status ?? 'todo',
            'assignee_id' => $request->assignee_id,
        ]);

        return $task;
    }

    public function show(Task $task)
    {
        return $task;
    }

    public function update(Request $request, Task $task)
    {
        $task->update($request->only([
            'title',
            'description',
            'status',
            'assignee_id'
        ]));

        return $task;
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return response()->noContent();
    }
}
