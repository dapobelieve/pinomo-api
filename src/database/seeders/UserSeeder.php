<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'System Administrator']
        );

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@corebanking.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // Only attach if not already attached
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);
    }
}