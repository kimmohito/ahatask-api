<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request, $projectId)
    {
        $query = Task::where('project_id', $projectId)
            ->where('organization_id', auth()->user()->organization_id);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->assignee_id) {
            $query->where('assignee_id', $request->assignee_id);
        }

        if ($request->sort === 'latest') {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate(20);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        $this->authorize('create tasks');

        $task = Task::create([
            'project_id' => $request->project_id,
            'organization_id' => auth()->user()->organization_id,
            'title' => $request->title,
            'description' => $request->description,
            'status' => 'todo',
        ]);

        return $task;
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Task $task)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        //
    }
}
