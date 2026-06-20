<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Facades\Hash;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. PERMISSIONS
        |--------------------------------------------------------------------------
        */

        $permissions = [
            'manage users',
            'manage projects',
            'create tasks',
            'edit tasks',
            'delete tasks',
            'view tasks',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. ROLES
        |--------------------------------------------------------------------------
        */

        $superadmin = Role::firstOrCreate([
            'name' => 'superadmin',
            'guard_name' => 'api',
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'api',
        ]);

        $member = Role::firstOrCreate([
            'name' => 'member',
            'guard_name' => 'api',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 3. ROLE PERMISSIONS
        |--------------------------------------------------------------------------
        */

        // SUPERADMIN → everything
        $superadmin->syncPermissions(Permission::all());

        // ADMIN → manage org + full task control
        $admin->syncPermissions([
            'manage users',
            'manage projects',
            'create tasks',
            'edit tasks',
            'delete tasks',
            'view tasks',
        ]);

        // MEMBER → limited access
        $member->syncPermissions([
            'create tasks',
            'edit tasks',
            'view tasks',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 4. ORGANIZATION
        |--------------------------------------------------------------------------
        */

        $org = Organization::firstOrCreate([
            'name' => 'Default Org',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 5. USERS
        |--------------------------------------------------------------------------
        */

        $superadminUser = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'organization_id' => $org->id,
            ]
        );

        $superadminUser->assignRole('superadmin');

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'organization_id' => $org->id,
            ]
        );

        $adminUser->assignRole('admin');

        $memberUser = User::firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'name' => 'Member User',
                'password' => Hash::make('password'),
                'organization_id' => $org->id,
            ]
        );

        $memberUser->assignRole('member');
    }
}
