<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Project extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'slug',
        'project_prefix',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new OrganizationScope());

        static::creating(function ($model) {
            if (empty($model->slug) && !empty($model->name)) {
                $base = Str::slug($model->name);
                do {
                    $suffix = sprintf('%08d', random_int(0, 99999999));
                    $candidate = $base . '-' . $suffix;
                } while (DB::table('projects')->where('slug', $candidate)->exists());
                $model->slug = $candidate;
            }
            if (empty($model->project_prefix) && !empty($model->name)) {
                // derive prefix: if single word use first two letters, otherwise first letters of first two words
                $parts = preg_split('/\s+/', trim($model->name));
                $parts = array_values(array_filter($parts, function ($p) { return $p !== ''; }));

                if (count($parts) === 1) {
                    $word = preg_replace('/[^A-Za-z0-9]/', '', $parts[0]);
                    $model->project_prefix = strtoupper(substr($word, 0, 2));
                } else {
                    $first = preg_replace('/[^A-Za-z0-9]/', '', $parts[0]);
                    $second = preg_replace('/[^A-Za-z0-9]/', '', $parts[1]);
                    $a = $first !== '' ? strtoupper($first[0]) : '';
                    $b = $second !== '' ? strtoupper($second[0]) : '';
                    $model->project_prefix = strtoupper(substr(($a . $b), 0, 2));
                }
                if (empty($model->project_prefix)) {
                    $model->project_prefix = strtoupper(substr($model->slug ?? '', 0, 2));
                }
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['organization_id', 'name', 'description'])
            ->logOnlyDirty()
            // ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $event) => "Project {$event}");
    }
}
