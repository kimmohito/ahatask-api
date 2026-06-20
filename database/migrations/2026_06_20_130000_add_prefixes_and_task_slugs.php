<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // add columns if missing
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'organization_prefix')) {
                $table->string('organization_prefix')->nullable()->before('name');
            }
            if (!Schema::hasColumn('organizations', 'slug')) {
                $table->string('slug')->nullable()->after('organization_prefix');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'slug')) {
                $table->string('slug')->nullable()->after('organization_id');
            }
            if (!Schema::hasColumn('projects', 'project_prefix')) {
                $table->string('project_prefix')->nullable()->after('slug');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'organization_slug')) {
                $table->string('organization_slug')->nullable()->after('organization_id');
            }
            if (!Schema::hasColumn('tasks', 'project_slug')) {
                $table->string('project_slug')->nullable()->after('project_id');
            }
            if (!Schema::hasColumn('tasks', 'slug')) {
                $table->string('slug')->nullable()->after('project_slug');
            }
            if (!Schema::hasColumn('tasks', 'task_number')) {
                $table->integer('task_number')->nullable()->after('slug');
            }
        });

        // helper closures to avoid repetition
        $generateUniqueSlug = function (string $table, string $name, ?string $existing = null) {
            if (!empty($existing)) return $existing;
            $base = Str::slug($name ?: 'item');
            do {
                $suffix = sprintf('%08d', random_int(0, 99999999));
                $candidate = $base . '-' . $suffix;
            } while (DB::table($table)->where('slug', $candidate)->exists());
            return $candidate;
        };

        $derivePrefix = function (?string $name, ?string $fallbackSlug = null) {
            $name = trim((string)($name ?? ''));
            if ($name === '') {
                return strtoupper(substr((string)($fallbackSlug ?? 'XX'), 0, 2));
            }
            $parts = preg_split('/\s+/', $name);
            $parts = array_values(array_filter($parts, function ($p) { return $p !== ''; }));
            if (count($parts) === 1) {
                $word = preg_replace('/[^A-Za-z0-9]/', '', $parts[0]);
                return strtoupper(substr($word, 0, 2));
            }
            $first = preg_replace('/[^A-Za-z0-9]/', '', $parts[0]);
            $second = preg_replace('/[^A-Za-z0-9]/', '', $parts[1]);
            $a = $first !== '' ? strtoupper($first[0]) : '';
            $b = $second !== '' ? strtoupper($second[0]) : '';
            $pref = strtoupper(substr(($a . $b), 0, 2));
            return $pref ?: strtoupper(substr((string)($fallbackSlug ?? 'XX'), 0, 2));
        };

        // backfill organizations using helper
        $orgs = DB::table('organizations')->get();
        foreach ($orgs as $org) {
            $slug = $generateUniqueSlug('organizations', $org->name, $org->slug ?? null);
            $prefix = $org->organization_prefix ?? $derivePrefix($org->name, $slug);
            DB::table('organizations')->where('id', $org->id)->update([
                'slug' => $slug,
                'organization_prefix' => $prefix,
            ]);
        }

        // backfill projects using helper
        $projects = DB::table('projects')->get();
        foreach ($projects as $project) {
            $slug = $generateUniqueSlug('projects', $project->name, $project->slug ?? null);
            $prefix = $project->project_prefix ?? $derivePrefix($project->name, $slug);
            DB::table('projects')->where('id', $project->id)->update([
                'slug' => $slug,
                'project_prefix' => $prefix,
            ]);
        }

        // backfill tasks: set project_slug, organization_slug, assign task_number per project and set slug
        $projects = DB::table('projects')->get();
        foreach ($projects as $project) {
            $projSlug = DB::table('projects')->where('id', $project->id)->value('slug');
            $projPrefix = DB::table('projects')->where('id', $project->id)->value('project_prefix');
            $orgSlug = DB::table('organizations')->where('id', $project->organization_id)->value('slug');

            $tasks = DB::table('tasks')->where('project_id', $project->id)->orderBy('id')->get();
            $n = 0;
            foreach ($tasks as $t) {
                $n++;
                $clean = preg_replace('/[^A-Za-z0-9]/', '', $projPrefix ?? '');
                $taskSlug = $clean !== '' ? strtoupper($clean) . '-' . $n : ($projSlug ?? 'P' . $project->id) . '-' . $n;
                DB::table('tasks')->where('id', $t->id)->update([
                    'organization_slug' => $orgSlug,
                    'project_slug' => $projSlug,
                    'slug' => $taskSlug,
                    'task_number' => $n,
                ]);
            }
        }

        // add uniqueness constraints after backfill
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'slug')) return;
            $table->unique('slug');
        });

        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'slug')) return;
            $table->unique('slug');
        });

        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'project_id') && Schema::hasColumn('tasks', 'task_number')) {
                $table->unique(['project_id', 'task_number']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'slug')) {
                $table->dropColumn('slug');
            }
            if (Schema::hasColumn('tasks', 'task_number')) {
                $table->dropColumn('task_number');
            }
            if (Schema::hasColumn('tasks', 'project_slug')) {
                $table->dropColumn('project_slug');
            }
            if (Schema::hasColumn('tasks', 'organization_slug')) {
                $table->dropColumn('organization_slug');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'project_prefix')) {
                $table->dropColumn('project_prefix');
            }
            if (Schema::hasColumn('projects', 'slug')) {
                $table->dropColumn('slug');
            }
        });

        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'organization_prefix')) {
                $table->dropColumn('organization_prefix');
            }
            if (Schema::hasColumn('organizations', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }
};
