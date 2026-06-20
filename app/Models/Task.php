<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use App\Policies\TaskPolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use App\Models\Project;
use App\Models\User;
use App\Models\Organization;

class Task extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'project_id',
        'organization_id',
        'project_slug',
        'organization_slug',
        'task_number',
        'slug',
        'title',
        'description',
        'status',
        'assignee_id',
    ];

    protected $policies = [
        Task::class => TaskPolicy::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status'])
            ->logOnlyDirty()
            // ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $event) => "Task {$event}");
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    protected static function booted()
    {
        static::addGlobalScope(new OrganizationScope());

        static::creating(function ($task) {
            // ensure project and organization slugs are set on create
            if (empty($task->project_slug) && $task->project_id) {
                $proj = Project::find($task->project_id);
                $task->project_slug = $proj?->slug ?? null;
            }

            if (empty($task->organization_slug) && $task->organization_id) {
                $org = Organization::find($task->organization_id);
                $task->organization_slug = $org?->slug ?? null;
            }

            // assign per-project incremental task_number to avoid collisions
            if (empty($task->task_number) && $task->project_id) {
                $max = \DB::table('tasks')->where('project_id', $task->project_id)->max('task_number');
                $task->task_number = ($max ?? 0) + 1;
            }

            // prepare slug as PREFIX-<task_number>
            if (empty($task->slug) && $task->project_id) {
                $proj = Project::find($task->project_id);
                $prefix = null;
                if ($proj) {
                    if (!empty($proj->project_prefix)) {
                        $prefix = $proj->project_prefix;
                    } else {
                        // derive prefix from project name similar to Project model
                        $name = $proj->name ?? '';
                        $parts = preg_split('/\s+/', trim($name));
                        $parts = array_values(array_filter($parts, function ($p) { return $p !== ''; }));
                        if (count($parts) === 1) {
                            $word = preg_replace('/[^A-Za-z0-9]/', '', $parts[0]);
                            $prefix = strtoupper(substr($word, 0, 2));
                        } else {
                            $first = preg_replace('/[^A-Za-z0-9]/', '', $parts[0]);
                            $second = preg_replace('/[^A-Za-z0-9]/', '', $parts[1]);
                            $a = $first !== '' ? strtoupper($first[0]) : '';
                            $b = $second !== '' ? strtoupper($second[0]) : '';
                            $prefix = strtoupper(substr(($a . $b), 0, 2));
                        }
                        if (empty($prefix)) {
                            $prefix = strtoupper(substr($proj->slug ?? '', 0, 2));
                        }
                    }
                }

                if ($prefix && $task->task_number) {
                    $clean = preg_replace('/[^A-Za-z0-9]/', '', $prefix);
                    $task->slug = strtoupper($clean) . '-' . $task->task_number;
                }
            }
        });
    }
}
