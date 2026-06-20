<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected static function booted()
    {
        static::addGlobalScope(new OrganizationScope());
    }

    use HasFactory;

    protected $fillable = [
        'project_id',
        'organization_id',
        'title',
        'description',
        'status',
        'assignee_id',
    ];

    public function activities()
    {
        return $this->hasMany(TaskActivity::class);
    }
}
