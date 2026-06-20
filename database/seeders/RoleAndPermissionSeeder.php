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
        // permissions
        $permissions = [
            'manage users',
            'manage projects',
            'create tasks',
            'edit tasks',
            'delete tasks',
            'view tasks',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'api'
            ]);
        }

        // roles
        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'api'
        ]);

        $member = Role::firstOrCreate([
            'name' => 'member',
            'guard_name' => 'api'
        ]);

        // assign permissions
        $admin->givePermissionTo($permissions);
        $member->givePermissionTo([
            'create tasks',
            'edit tasks',
            'view tasks'
        ]);

        // org
        $org = Organization::firstOrCreate([
            'name' => 'Default Org'
        ]);

        // admin user
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'organization_id' => $org->id
            ]
        );

        $adminUser->assignRole('admin');

        // member user
        $memberUser = User::firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'name' => 'Member User',
                'password' => Hash::make('password'),
                'organization_id' => $org->id
            ]
        );

        $memberUser->assignRole('member');
    }
}
