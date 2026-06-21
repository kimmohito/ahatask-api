<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $assignee = $this->whenLoaded('assignee');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'task_slug' => $this->slug,
            'task_number' => $this->task_number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'assignee_id' => $this->assignee_id,
            'assignee_name' => $this->when($this->assignee_id || $assignee, fn() => $assignee?->name ?? $this->assignee_name ?? null),
            'assignee' => $assignee ? [
                'id' => $assignee->id,
                'name' => $assignee->name ?? $assignee->username ?? $assignee->email,
            ] : null,
            'project' => $this->whenLoaded('project', fn() => [
                'id' => $this->project?->id,
                'slug' => $this->project?->slug,
                'name' => $this->project?->name,
            ]),
            'organization' => $this->whenLoaded('organization', fn() => [
                'id' => $this->organization?->id,
                'slug' => $this->organization?->slug,
                'name' => $this->organization?->name,
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
