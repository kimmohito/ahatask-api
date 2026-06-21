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
            'assignee_name' => $this->assignee?->name ?? $this->assignee_name ?? null,
            'assignee' => $this->whenLoaded('assignee', fn() => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name ?? $this->assignee->username ?? $this->assignee->email,
            ], null),
            'favorite_users' => $this->whenLoaded('favorites', fn() => $this->favorites
                ->map(fn($user) => $user->name ?? $user->username ?? $user->email)
                ->filter()
                ->values()
                ->all(), []),
            'favorites_count' => $this->whenLoaded('favorites', fn() => $this->favorites->count(), 0),
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
