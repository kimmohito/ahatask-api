<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OAAttr;

/**
 * @OA\Tag(name="Dashboard", description="Organization dashboard overview")
 */

class DashboardController extends Controller
{
    private const CACHE_VERSION = 'v2';

    #[OAAttr\Get(
        path: '/api/dashboard',
        tags: ['Dashboard'],
        summary: 'Get dashboard overview',
        security: [['bearerAuth' => []]],
        parameters: [
            new OAAttr\Parameter(name: 'priority_limit', in: 'query', required: false, schema: new OAAttr\Schema(type: 'integer', example: 6)),
            new OAAttr\Parameter(name: 'due_limit', in: 'query', required: false, schema: new OAAttr\Schema(type: 'integer', example: 4)),
            new OAAttr\Parameter(name: 'your_limit', in: 'query', required: false, schema: new OAAttr\Schema(type: 'integer', example: 10)),
        ],
        responses: [
            new OAAttr\Response(response: 200, description: 'Dashboard data'),
            new OAAttr\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    /**
     * @OA\Get(
     *     path="/api/dashboard",
     *     tags={"Dashboard"},
     *     summary="Get dashboard overview",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="priority_limit", in="query", required=false, @OA\Schema(type="integer", example=6)),
     *     @OA\Parameter(name="due_limit", in="query", required=false, @OA\Schema(type="integer", example=4)),
     *     @OA\Parameter(name="your_limit", in="query", required=false, @OA\Schema(type="integer", example=10)),
     *     @OA\Response(response=200, description="Dashboard data"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function overview(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $cacheKey = 'tasks:dashboard:' . self::CACHE_VERSION . ':' . $user->id . ':' . md5(serialize($request->all()));
        $ttl = 60;

        $payload = $this->cacheRemember($cacheKey, $ttl, function () use ($request, $user) {
            $priorityLimit = max(1, min(50, (int) $request->get('priority_limit', 6)));
            $dueLimit = max(1, min(50, (int) $request->get('due_limit', 4)));
            $yourLimit = max(1, min(50, (int) $request->get('your_limit', 10)));

            $summary = $this->summaryCounts($request);

            $priorityTasks = $this->baseQuery($request)
                ->whereIn('priority', ['urgent', 'high', 'medium'])
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->orderByDesc('updated_at')
                ->limit($priorityLimit)
                ->get();

            if ($priorityTasks->isEmpty()) {
                $priorityTasks = $this->baseQuery($request)
                    ->orderByDesc('updated_at')
                    ->limit($priorityLimit)
                    ->get();
            }

            $dueTasks = $this->baseQuery($request)
                ->whereIn('status', ['todo', 'open', 'grooming', 'backlog', 'doing', 'in progress', 'in_progress'])
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->orderBy('created_at')
                ->limit($dueLimit)
                ->get();

            if ($dueTasks->isEmpty()) {
                $dueTasks = $this->baseQuery($request)
                    ->orderBy('created_at')
                    ->limit($dueLimit)
                    ->get();
            }

            $yourTasks = $this->baseQuery($request)
                ->where('assignee_id', $user->id)
                ->whereNotIn('status', ['done', 'completed', 'complete'])
                ->orderByDesc('updated_at')
                ->limit($yourLimit)
                ->get();

            if ($yourTasks->isEmpty()) {
                $yourTasks = $this->baseQuery($request)
                    ->where('assignee_id', $user->id)
                    ->orderByDesc('updated_at')
                    ->limit($yourLimit)
                    ->get();
            }

            $activity = $this->weeklyActivity($request);

            return [
                'summary' => $summary,
                'task_groups' => [
                    'top_priority_tasks' => $priorityTasks->map(fn(Task $task) => $this->toTaskCard($task, 'High priority'))->values(),
                    'due_tasks' => $dueTasks->map(fn(Task $task) => $this->toTaskCard($task, 'Due soon'))->values(),
                    'your_tasks' => $yourTasks->map(fn(Task $task) => $this->toTaskCard($task, 'Assigned to you'))->values(),
                ],
                'activity' => $activity,
                'overview' => [
                    'total_owned' => $this->baseQuery($request)->where('assignee_id', $user->id)->count(),
                    'total_all' => $summary['total_tasks'],
                    'by_status' => [
                        'todo' => $summary['todo'],
                        'in_progress' => $summary['in_progress'],
                        'completed' => $summary['completed'],
                        'other' => max(0, $summary['total_tasks'] - ($summary['todo'] + $summary['in_progress'] + $summary['completed'])),
                    ],
                ],
            ];
        });

        return response()->json(['data' => $payload]);
    }

    private function baseQuery(Request $request)
    {
        $query = Task::query()->with('project:id,name,slug');

        if ($request->filled('project')) {
            $project = $request->get('project');
            if (is_numeric($project)) {
                $query->where('project_id', (int) $project);
            } else {
                $query->where('project_slug', (string) $project);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->get('status'));
        }

        return $query;
    }

    private function summaryCounts(Request $request): array
    {
        $query = $this->baseQuery($request);

        return [
            'total_tasks' => (clone $query)->count(),
            'todo' => (clone $query)->whereIn('status', ['todo', 'open', 'grooming', 'backlog'])->count(),
            'in_progress' => (clone $query)->whereIn('status', ['in progress', 'in_progress', 'doing', 'progress'])->count(),
            'completed' => (clone $query)->whereIn('status', ['done', 'completed', 'complete'])->count(),
        ];
    }

    private function weeklyActivity(Request $request): array
    {
        $start = CarbonImmutable::now()->startOfWeek();
        $end = $start->addDays(6)->endOfDay();

        $rows = $this->baseQuery($request)
            ->selectRaw('assignee_id, DATE(updated_at) as day, COUNT(*) as total')
            ->whereNotNull('assignee_id')
            ->whereIn('status', ['done', 'completed', 'complete'])
            ->whereBetween('updated_at', [$start, $end])
            ->groupBy('assignee_id', 'day')
            ->get();

        $labels = [];
        for ($i = 0; $i < 7; $i++) {
            $labels[] = $start->addDays($i)->format('D');
        }

        $byUserDay = [];
        $totals = [];

        foreach ($rows as $row) {
            $userId = (int) $row->assignee_id;
            $dayIndex = CarbonImmutable::parse($row->day)->diffInDays($start);
            if ($dayIndex < 0 || $dayIndex > 6) {
                continue;
            }

            if (!array_key_exists($userId, $byUserDay)) {
                $byUserDay[$userId] = array_fill(0, 7, 0);
                $totals[$userId] = 0;
            }

            $count = (int) $row->total;
            $byUserDay[$userId][$dayIndex] = $count;
            $totals[$userId] += $count;
        }

        arsort($totals);
        $topUserIds = array_slice(array_keys($totals), 0, 7);
        $userNames = User::query()->whereIn('id', $topUserIds)->pluck('name', 'id');

        $users = collect($topUserIds)->map(function ($userId) use ($userNames, $byUserDay) {
            return [
                'user_id' => (int) $userId,
                'name' => $userNames[(int) $userId] ?? ('User ' . $userId),
                'values' => $byUserDay[(int) $userId] ?? array_fill(0, 7, 0),
            ];
        })->values();

        return [
            'labels' => $labels,
            'users' => $users,
            'total_completed_this_week' => array_sum($totals),
        ];
    }

    private function normalizeStatus(?string $status): string
    {
        $value = strtolower(trim((string) $status));

        if (in_array($value, ['done', 'completed', 'complete'], true)) {
            return 'completed';
        }

        if (in_array($value, ['in progress', 'in_progress', 'doing', 'progress'], true)) {
            return 'in_progress';
        }

        if (in_array($value, ['todo', 'grooming', 'backlog'], true)) {
            return 'todo';
        }

        if ($value === 'open') {
            return 'todo';
        }

        return $value !== '' ? $value : 'unknown';
    }

    private function toTaskCard(Task $task, string $subtitle): array
    {
        return [
            'id' => $task->id,
            'slug' => $task->slug,
            'title' => $task->title,
            'subtitle' => $subtitle,
            'status' => $this->normalizeStatus($task->status),
            'priority' => $task->priority,
            'project' => [
                'id' => $task->project?->id,
                'name' => $task->project?->name,
                'slug' => $task->project?->slug,
            ],
        ];
    }

    private function cacheRemember(string $key, $ttl, callable $fn)
    {
        try {
            return Cache::store('redis')->remember($key, $ttl, $fn);
        } catch (\Throwable $e) {
            return Cache::remember($key, $ttl, $fn);
        }
    }
}
