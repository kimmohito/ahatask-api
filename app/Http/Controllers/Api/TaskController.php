<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskComment;
use App\Http\Resources\TaskResource;
use App\Http\Requests\StoreTaskRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Spatie\Activitylog\Models\Activity;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OAAttr;

/**
 * @OA\Tag(name="Tasks", description="Task management")
 */

class TaskController extends Controller
{
    #[OAAttr\Get(
        path: '/api/tasks',
        tags: ['Tasks'],
        summary: 'List tasks',
        security: [['bearerAuth' => []]],
        parameters: [
            new OAAttr\Parameter(name: 'project', in: 'query', required: false, schema: new OAAttr\Schema(type: 'string')),
            new OAAttr\Parameter(name: 'status', in: 'query', required: false, schema: new OAAttr\Schema(type: 'string')),
            new OAAttr\Parameter(name: 'priority', in: 'query', required: false, schema: new OAAttr\Schema(type: 'string')),
            new OAAttr\Parameter(name: 'assignee_id', in: 'query', required: false, schema: new OAAttr\Schema(type: 'integer')),
            new OAAttr\Parameter(name: 'page', in: 'query', required: false, schema: new OAAttr\Schema(type: 'integer')),
            new OAAttr\Parameter(name: 'per_page', in: 'query', required: false, schema: new OAAttr\Schema(type: 'integer')),
        ],
        responses: [new OAAttr\Response(response: 200, description: 'Paginated tasks')]
    )]
    /**
     * @OA\Get(
     *     path="/api/tasks",
     *     tags={"Tasks"},
     *     summary="List tasks",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="project", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="priority", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="assignee_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Paginated tasks")
     * )
     */
    public function index(Request $request)
    {
        try {
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

        // Build a cache key from user and request parameters
        $userId = $user->id ?? 'guest';
        $cacheKey = 'tasks:index:' . $userId . ':' . md5(serialize([
            'params' => $request->all(),
            'per_page' => $perPage,
            'page' => $page,
        ]));

        $ttl = 60; // seconds

        $result = $this->cacheRemember($cacheKey, $ttl, function () use ($query, $perPage, $page) {
            $paginator = $query->paginate($perPage, ['*'], 'page', max(1, $page));
            // Convert resource collection to array payload suitable for JSON caching
            $data = TaskResource::collection($paginator)->response()->getData(true);
            return $data;
        });

            return response()->json($result);
        } catch (\Throwable $e) {
            \Log::error('TaskController@index error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $msg = config('app.debug') ? $e->getMessage() : 'Internal Server Error';
            return response()->json(['message' => 'Server error', 'error' => $msg], 500);
        }
    }

    #[OAAttr\Post(
        path: '/api/tasks',
        tags: ['Tasks'],
        summary: 'Create a task',
        security: [['bearerAuth' => []]],
        requestBody: new OAAttr\RequestBody(
            required: true,
            content: new OAAttr\JsonContent(
                required: ['project_id', 'title'],
                properties: [
                    new OAAttr\Property(property: 'project_id', type: 'integer', example: 1),
                    new OAAttr\Property(property: 'title', type: 'string', example: 'Fix login'),
                    new OAAttr\Property(property: 'description', type: 'string', example: 'Add better validation'),
                    new OAAttr\Property(property: 'status', type: 'string', example: 'todo'),
                    new OAAttr\Property(property: 'priority', type: 'string', example: 'high'),
                    new OAAttr\Property(property: 'assignee_id', type: 'integer', example: 2),
                ]
            )
        ),
        responses: [
            new OAAttr\Response(response: 201, description: 'Task created'),
            new OAAttr\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * @OA\Post(
     *     path="/api/tasks",
     *     tags={"Tasks"},
     *     summary="Create a task",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"project_id","title"}, @OA\Property(property="project_id", type="integer", example=1), @OA\Property(property="title", type="string", example="Fix login"), @OA\Property(property="description", type="string", example="Add better validation"), @OA\Property(property="status", type="string", example="todo"), @OA\Property(property="priority", type="string", example="high"), @OA\Property(property="assignee_id", type="integer", example=2))),
     *     @OA\Response(response=201, description="Task created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreTaskRequest $request)
    {
        $validated = $request->validated();

        $task = Task::create([
            'organization_id' => $request->user()->organization_id,
            'project_id' => $validated['project_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'todo',
            'priority' => $validated['priority'] ?? null,
            'assignee_id' => $validated['assignee_id'] ?? null,
        ]);

        // Invalidate related caches
        $this->flushTaskCaches();

        return new TaskResource($task->load(['assignee', 'project', 'organization']));
    }

    #[OAAttr\Get(
        path: '/api/tasks/{task}',
        tags: ['Tasks'],
        summary: 'Get a task',
        security: [['bearerAuth' => []]],
        parameters: [new OAAttr\Parameter(name: 'task', in: 'path', required: true, schema: new OAAttr\Schema(type: 'string'))],
        responses: [new OAAttr\Response(response: 200, description: 'Task detail')]
    )]
    /**
     * @OA\Get(
     *     path="/api/tasks/{task}",
     *     tags={"Tasks"},
     *     summary="Get a task",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="task", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Task detail")
     * )
     */
    public function show(Task $task)
    {
        return new TaskResource($task->load(['assignee', 'project', 'organization', 'favorites']));
    }

    /**
     * @OA\Get(
     *     path="/api/tasks/{task}/history",
     *     tags={"Tasks"},
     *     summary="Get task history",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="task", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Task history")
     * )
     */
    public function history(Task $task)
    {
        $activities = Activity::query()
            ->where('subject_type', Task::class)
            ->where('subject_id', $task->id)
            ->latest()
            ->limit(100)
            ->get();

        $data = $activities->map(function (Activity $activity) {
            $properties = $activity->properties ?? [];
            if (is_string($properties)) {
                $properties = json_decode($properties, true) ?: [];
            }
            if ($properties instanceof \Illuminate\Support\Collection) {
                $properties = $properties->toArray();
            }
            if (!is_array($properties)) {
                $properties = [];
            }

            $attributes = $properties['attributes'] ?? [];
            $old = $properties['old'] ?? [];

            $changes = [];
            if (is_array($attributes)) {
                foreach ($attributes as $field => $after) {
                    $before = is_array($old) && array_key_exists($field, $old) ? $old[$field] : null;
                    if ($before !== $after) {
                        $changes[] = [
                            'field' => (string) $field,
                            'before' => $before,
                            'after' => $after,
                        ];
                    }
                }
            }

            $causerName = null;
            if ($activity->causer && isset($activity->causer->name)) {
                $causerName = $activity->causer->name;
            }

            return [
                'description' => $activity->description ?? ($activity->event ? "Task {$activity->event}" : 'Task updated'),
                'action' => $activity->event,
                'created_at' => optional($activity->created_at)->toDateTimeString(),
                'user_name' => $causerName,
                'actor_name' => $causerName,
                'changes' => $changes,
                'source' => 'server',
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    /**
     * @OA\Get(
     *     path="/api/tasks/{task}/comments",
     *     tags={"Tasks"},
     *     summary="List task comments",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="task", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Task comments")
     * )
     */
    public function comments(Task $task)
    {
        $comments = TaskComment::query()
            ->with('user:id,name,email')
            ->where('task_id', $task->id)
            ->latest()
            ->limit(100)
            ->get();

        $data = $comments->map(function (TaskComment $comment) {
            $name = $comment->user?->name ?? $comment->user?->email ?? 'Unknown user';

            return [
                'id' => $comment->id,
                'body' => $comment->body,
                'comment' => $comment->body,
                'created_at' => optional($comment->created_at)->toDateTimeString(),
                'user_name' => $name,
                'actor_name' => $name,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    /**
     * @OA\Post(
     *     path="/api/tasks/{task}/comments",
     *     tags={"Tasks"},
     *     summary="Add a task comment",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="task", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"body"}, @OA\Property(property="body", type="string", example="Looks good"))),
     *     @OA\Response(response=201, description="Comment created")
     * )
     */
    public function storeComment(Request $request, Task $task)
    {
        $payload = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'organization_id' => $task->organization_id,
            'user_id' => optional($request->user())->id,
            'body' => trim($payload['body']),
        ]);

        $comment->load('user:id,name,email');
        $name = $comment->user?->name ?? $comment->user?->email ?? 'Unknown user';

        return response()->json([
            'data' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'comment' => $comment->body,
                'created_at' => optional($comment->created_at)->toDateTimeString(),
                'user_name' => $name,
                'actor_name' => $name,
            ],
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/tasks/{task}/favorite",
     *     tags={"Tasks"},
     *     summary="Favorite a task",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="task", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Task favorited")
     * )
     */
    public function favorite(Request $request, Task $task)
    {
        $userId = optional($request->user())->id;
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        DB::table('task_favorites')->updateOrInsert(
            ['task_id' => $task->id, 'user_id' => $userId],
            ['updated_at' => now(), 'created_at' => now()]
        );

        return response()->json([
            'data' => [
                'favorited' => true,
                'favorite_users' => $this->favoriteUsersForTask($task),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/tasks/{task}/favorite",
     *     tags={"Tasks"},
     *     summary="Remove task favorite",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="task", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Task unfavorited")
     * )
     */
    public function unfavorite(Request $request, Task $task)
    {
        $userId = optional($request->user())->id;
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        DB::table('task_favorites')
            ->where('task_id', $task->id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json([
            'data' => [
                'favorited' => false,
                'favorite_users' => $this->favoriteUsersForTask($task),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tasks/{task}/bookmark",
     *     tags={"Tasks"},
     *     summary="Bookmark a task",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="task", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Task bookmarked")
     * )
     */
    public function bookmark(Request $request, Task $task)
    {
        return $this->toggleTaskRelation($request, $task, 'task_bookmarks', true, 'bookmarked');
    }

    /**
     * @OA\Delete(
     *     path="/api/tasks/{task}/bookmark",
     *     tags={"Tasks"},
     *     summary="Remove task bookmark",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="task", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Task bookmark removed")
     * )
     */
    public function unbookmark(Request $request, Task $task)
    {
        return $this->toggleTaskRelation($request, $task, 'task_bookmarks', false, 'bookmarked');
    }

    /**
     * @OA\Post(
     *     path="/api/tasks/{task}/pin",
     *     tags={"Tasks"},
     *     summary="Pin a task",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="task", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Task pinned")
     * )
     */
    public function pin(Request $request, Task $task)
    {
        return $this->toggleTaskRelation($request, $task, 'task_pins', true, 'pinned');
    }

    /**
     * @OA\Delete(
     *     path="/api/tasks/{task}/pin",
     *     tags={"Tasks"},
     *     summary="Unpin a task",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="task", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Task unpinned")
     * )
     */
    public function unpin(Request $request, Task $task)
    {
        return $this->toggleTaskRelation($request, $task, 'task_pins', false, 'pinned');
    }

    #[OAAttr\Put(
        path: '/api/tasks/{task}',
        tags: ['Tasks'],
        summary: 'Update a task',
        security: [['bearerAuth' => []]],
        parameters: [new OAAttr\Parameter(name: 'task', in: 'path', required: true, schema: new OAAttr\Schema(type: 'string'))],
        requestBody: new OAAttr\RequestBody(
            required: true,
            content: new OAAttr\JsonContent(
                properties: [
                    new OAAttr\Property(property: 'title', type: 'string', example: 'Fix login'),
                    new OAAttr\Property(property: 'description', type: 'string', example: 'Updated description'),
                    new OAAttr\Property(property: 'status', type: 'string', example: 'doing'),
                    new OAAttr\Property(property: 'priority', type: 'string', example: 'high'),
                    new OAAttr\Property(property: 'assignee_id', type: 'integer', example: 2),
                ]
            )
        ),
        responses: [new OAAttr\Response(response: 200, description: 'Task updated')]
    )]
    /**
     * @OA\Put(
     *     path="/api/tasks/{task}",
     *     tags={"Tasks"},
     *     summary="Update a task",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="task", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="title", type="string", example="Fix login"), @OA\Property(property="description", type="string", example="Updated description"), @OA\Property(property="status", type="string", example="doing"), @OA\Property(property="priority", type="string", example="high"), @OA\Property(property="assignee_id", type="integer", example=2))),
     *     @OA\Response(response=200, description="Task updated")
     * )
     */
    public function update(Request $request, Task $task)
    {
        $data = $request->only(['title', 'description', 'status', 'assignee_id', 'priority']);
        $task->fill(array_filter($data, fn($v) => $v !== null));
        $task->save();

        $this->flushTaskCaches();
        return new TaskResource($task->fresh());
    }

    #[OAAttr\Delete(
        path: '/api/tasks/{task}',
        tags: ['Tasks'],
        summary: 'Delete a task',
        security: [['bearerAuth' => []]],
        parameters: [new OAAttr\Parameter(name: 'task', in: 'path', required: true, schema: new OAAttr\Schema(type: 'string'))],
        responses: [new OAAttr\Response(response: 204, description: 'Task deleted')]
    )]
    /**
     * @OA\Delete(
     *     path="/api/tasks/{task}",
     *     tags={"Tasks"},
     *     summary="Delete a task",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="task", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Task deleted")
     * )
     */
    public function destroy(Task $task)
    {
        $task->delete();
        $this->flushTaskCaches();
        return response()->json([], 204);
    }

    private function flushTaskCaches()
    {
        try {
            $redis = Redis::connection();
            $patterns = [
                'tasks:index:*',
                'tasks:users:*',
                'tasks:priorities:*',
                'tasks:statuses:*',
                'tasks:dashboard:*',
            ];
            foreach ($patterns as $p) {
                $keys = $redis->keys($p);
                if (!empty($keys)) {
                    $redis->del($keys);
                }
            }
        } catch (\Exception $e) {
            // ignore cache flush errors
        }
    }

    /**
     * @OA\Get(
     *     path="/api/statuses",
     *     tags={"Tasks"},
     *     summary="Get available statuses",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Status list")
     * )
     */
    public function statuses(Request $request)
    {
        $cacheKey = 'tasks:statuses:' . md5(serialize($request->all()));
        $ttl = 60 * 5;
        $list = $this->cacheRemember($cacheKey, $ttl, function () {
            $list = Task::query()->distinct()->pluck('status')->filter()->values()->all();
            if (empty($list)) {
                $list = ['todo', 'grooming', 'in progress', 'done'];
            }
            return $list;
        });

        return response()->json(['data' => $list]);
    }

    /**
     * @OA\Get(
     *     path="/api/priorities",
     *     tags={"Tasks"},
     *     summary="Get available priorities",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Priority list")
     * )
     */
    public function priorities(Request $request)
    {
        $cacheKey = 'tasks:priorities:' . md5(serialize($request->all()));
        $ttl = 60 * 5;
        $list = $this->cacheRemember($cacheKey, $ttl, function () {
            $list = Task::query()->distinct()->pluck('priority')->filter()->values()->all();
            if (empty($list)) {
                $list = ['low', 'normal', 'high', 'urgent'];
            }
            return $list;
        });

        return response()->json(['data' => $list]);
    }

    /**
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Tasks"},
     *     summary="Get assignable users",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="project", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="only_assignable", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="User list")
     * )
     */
    public function users(Request $request)
    {
        $search = trim((string)($request->get('q') ?? $request->get('query') ?? $request->get('search') ?? ''));

        $cacheKey = 'tasks:users:' . md5(serialize($request->all()));
        $ttl = 60 * 5;

        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            return response()->json(['data' => $cached]);
        }

        $onlyAssignable = filter_var($request->get('only_assignable', false), FILTER_VALIDATE_BOOLEAN);
        $projectParam = $request->get('project') ?? $request->get('project_id');

        $userIds = null;

        if ($projectParam) {
            // try to resolve project id by slug or id
            $projModel = null;
            if (is_numeric($projectParam)) {
                $projModel = \App\Models\Project::find((int)$projectParam);
            } else {
                $projModel = \App\Models\Project::where('slug', $projectParam)->first();
            }

            if ($projModel) {
                // prefer project users pivot
                $userIds = $projModel->users()->pluck('users.id')->unique()->filter()->values()->toArray();
            } else {
                // fallback: collect assignees from tasks in that project
                $userIds = \Illuminate\Support\Facades\DB::table('tasks')
                    ->when(!is_numeric($projectParam), fn($q) => $q->where('project_slug', $projectParam), fn($q) => $q->where('project_id', (int)$projectParam))
                    ->whereNotNull('assignee_id')
                    ->distinct()
                    ->pluck('assignee_id')
                    ->filter()
                    ->values()
                    ->toArray();
            }
        }

        $userQuery = \App\Models\User::query();

        if ($onlyAssignable) {
            if ($userIds === null) {
                // no project specified, nothing assignable
                $out = [];
                $this->cachePut($cacheKey, $out, $ttl);
                return response()->json(['data' => $out]);
            }
            $userQuery->whereIn('id', $userIds);
        } else {
            // if project provided, prefer project users but allow broader search
            if ($userIds !== null && !empty($userIds)) {
                $userQuery->whereIn('id', $userIds);
            }
        }

        if ($search !== '') {
            $userQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $userQuery->limit(200)->get(['id', 'name', 'email']);

        // normalize to simple objects expected by frontend
        $out = $users->map(fn($u) => ['id' => $u->id, 'name' => $u->name ?? $u->email])->values()->toArray();

        $this->cachePut($cacheKey, $out, $ttl);

        return response()->json(['data' => $out]);
    }

    private function cacheRemember(string $key, $ttl, callable $fn)
    {
        try {
            return Cache::store('redis')->remember($key, $ttl, $fn);
        } catch (\Throwable $e) {
            return Cache::remember($key, $ttl, $fn);
        }
    }

    private function cacheGet(string $key)
    {
        try {
            return Cache::store('redis')->get($key);
        } catch (\Throwable $e) {
            return Cache::get($key);
        }
    }

    private function cachePut(string $key, $value, $ttl)
    {
        try {
            Cache::store('redis')->put($key, $value, $ttl);
        } catch (\Throwable $e) {
            Cache::put($key, $value, $ttl);
        }
    }

    private function toggleTaskRelation(Request $request, Task $task, string $table, bool $enabled, string $key)
    {
        $userId = optional($request->user())->id;
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($enabled) {
            DB::table($table)->updateOrInsert(
                ['task_id' => $task->id, 'user_id' => $userId],
                ['updated_at' => now(), 'created_at' => now()]
            );
        } else {
            DB::table($table)
                ->where('task_id', $task->id)
                ->where('user_id', $userId)
                ->delete();
        }

        return response()->json(['data' => [$key => $enabled]]);
    }

    private function favoriteUsersForTask(Task $task): array
    {
        return DB::table('task_favorites')
            ->join('users', 'users.id', '=', 'task_favorites.user_id')
            ->where('task_favorites.task_id', $task->id)
            ->orderBy('users.name')
            ->pluck('users.name')
            ->filter()
            ->values()
            ->all();
    }
}
