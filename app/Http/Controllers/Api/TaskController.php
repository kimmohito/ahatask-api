<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Http\Resources\TaskResource;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = Task::query()->with(['project', 'assignee', 'organization']);

        // Organization / Project scoping (accept slugs or numeric ids)
        if ($request->filled('org')) {
            $org = $request->get('org');
            if (is_numeric($org)) {
                $query->where('organization_id', (int)$org);
            } else {
                $query->where('organization_slug', $org);
            }
        }

        if ($request->filled('project')) {
            $proj = $request->get('project');
            if (is_numeric($proj)) {
                $query->where('project_id', (int)$proj);
            } else {
                $query->where('project_slug', $proj);
            }
        }

        // Basic filters
        $query->when($request->filled('status'), fn($q) => $q->where('status', $request->get('status')));
        $query->when($request->filled('priority'), fn($q) => $q->where('priority', $request->get('priority')));
        $query->when($request->filled('assignee_id'), fn($q) => $q->where('assignee_id', $request->get('assignee_id')));

        // support CSV or array for assignee_in/priorities
        if ($request->filled('priority_in') || $request->filled('priorities')) {
            $vals = $request->get('priority_in', $request->get('priorities'));
            $arr = is_array($vals) ? $vals : array_filter(array_map('trim', explode(',', (string)$vals)));
            if (!empty($arr)) $query->whereIn('priority', $arr);
        }

        if ($request->filled('assignee_in') || $request->filled('assignees')) {
            $vals = $request->get('assignee_in', $request->get('assignees'));
            $arr = is_array($vals) ? $vals : array_filter(array_map('trim', explode(',', (string)$vals)));
            if (!empty($arr)) $query->whereIn('assignee_id', $arr);
        }

        // project_id filter
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->get('project_id'));
        }

        // date range
        $query->when($request->filled('from'), fn($q) => $q->where('created_at', '>=', $request->get('from')));
        $query->when($request->filled('to'), fn($q) => $q->where('created_at', '<=', $request->get('to')));

        // full text-ish search across common fields
        $search = $request->get('q') ?? $request->get('query') ?? $request->get('search') ?? $request->get('keyword');
        if ($search) {
            $search = trim((string)$search);

            // support tokenized search: assignee:NAME and priority:VALUE
            if (preg_match('/assignee:([^\s]+)/i', $search, $m)) {
                $name = trim($m[1]);
                $query->whereHas('assignee', fn($q) => $q->where('name', 'like', "%{$name}%"));
            }

            if (preg_match('/priority:([^\s]+)/i', $search, $m)) {
                $prio = trim($m[1]);
                $query->where('priority', $prio);
            }

            // general search across task fields and assignee name
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('priority', 'like', "%{$search}%")
                  ->orWhereHas('assignee', fn($qa) => $qa->where('name', 'like', "%{$search}%"));
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by') ?? $request->get('order_by') ?? 'created_at';
        $sortDir = strtolower($request->get('sort_dir') ?? $request->get('order_dir') ?? $request->get('direction') ?? 'desc');
        $allowedSorts = ['title', 'status', 'priority', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSorts)) $sortBy = 'created_at';
        if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = (int) $request->get('per_page', $request->get('perPage', 10));
        $perPage = $perPage <= 0 ? 10 : $perPage;
        $allowed = [5,10,20,50,100,1000];
        if (!in_array($perPage, $allowed)) $perPage = 10;

        $page = (int) $request->get('page', $request->get('current_page', 1));

        $paginator = $query->paginate($perPage, ['*'], 'page', max(1, $page));

        return TaskResource::collection($paginator);
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

        return new TaskResource($task->load(['assignee', 'project', 'organization']));
    }

    public function show(Task $task)
    {
        return new TaskResource($task->load(['assignee', 'project', 'organization']));
    }

    public function update(Request $request, Task $task)
    {
        $data = $request->only(['title', 'description', 'status', 'assignee_id', 'priority']);
        $task->fill(array_filter($data, fn($v) => $v !== null));
        $task->save();

        return new TaskResource($task->fresh());
    }

    public function destroy(Task $task)
    {
        $task->delete();
        return response()->json([], 204);
    }

    /**
     * Return available statuses. Derived from tasks or fallback defaults.
     */
    public function statuses(Request $request)
    {
        $list = Task::query()->distinct()->pluck('status')->filter()->values()->all();
        if (empty($list)) {
            $list = ['todo', 'grooming', 'in progress', 'done'];
        }

        return response()->json(['data' => $list]);
    }

    /**
     * Return available priorities. Derived from tasks or fallback defaults.
     */
    public function priorities(Request $request)
    {
        $list = Task::query()->distinct()->pluck('priority')->filter()->values()->all();
        if (empty($list)) {
            $list = ['low', 'normal', 'high', 'urgent'];
        }

        return response()->json(['data' => $list]);
    }

    /**
     * Return users. If `project` is provided, return users assigned in that project first.
     */
    public function users(Request $request)
    {
        $search = trim((string)($request->get('q') ?? $request->get('query') ?? $request->get('search') ?? ''));

        $userQuery = \App\Models\User::query();

        if ($request->filled('project')) {
            $proj = $request->get('project');
            $userQuery->whereIn('id', function ($q) use ($proj) {
                $q->select('assignee_id')->from('tasks')
                    ->when(!is_numeric($proj), fn($qq) => $qq->where('project_slug', $proj), fn($qq) => $qq->where('project_id', (int)$proj))
                    ->whereNotNull('assignee_id');
            });
        }

        if ($search !== '') {
            $userQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $userQuery->limit(200)->get(['id', 'name', 'email']);

        // normalize to simple objects expected by frontend
        $out = $users->map(fn($u) => ['id' => $u->id, 'name' => $u->name ?? $u->email])->values();

        return response()->json(['data' => $out]);
    }
}
