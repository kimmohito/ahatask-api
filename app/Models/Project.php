<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected static function booted()
    {
        static::addGlobalScope(new OrganizationScope());
    }

    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'description'
    ];

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
