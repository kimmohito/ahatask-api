<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create roles ONLY (NO assignRole here)
        Role::firstOrCreate([
            'name' => 'superadmin',
            'guard_name' => 'api',
        ]);

        Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'api',
        ]);

        Role::firstOrCreate([
            'name' => 'user',
            'guard_name' => 'api',
        ]);

        // 2. Create user
        $kimmohito = User::firstOrCreate(
            ['email' => 'kimmohito@gmail.com'],
            [
                'name' => 'Kim Mohito',
                'password' => Hash::make('4thJune1996!'),
            ]
        );

        // 3. Assign role TO USER (this is correct)
        $kimmohito->assignRole('user');
    }
}
