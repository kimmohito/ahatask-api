<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'project_id' => $this->input('project_id', $this->input('projectId', $this->input('project'))),
            'title' => $this->input('title', $this->input('task_title', $this->input('name'))),
            'description' => $this->input('description', $this->input('details', $this->input('body'))),
            'status' => $this->input('status', $this->input('state', 'todo')),
            'priority' => $this->input('priority', $this->input('priority_level')),
            'assignee_id' => $this->input('assignee_id', $this->input('assigneeId')),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create tasks');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:todo,doing,done,open,in progress,in_progress,backlog,grooming,complete,completed'],
            'priority' => ['nullable', 'in:low,medium,high,normal,urgent'],
            'assignee_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
