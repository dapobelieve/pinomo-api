<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'description' => 'System Administrator'],
            ['name' => 'manager', 'description' => 'Bank Manager'],
            ['name' => 'teller', 'description' => 'Bank Teller'],
            ['name' => 'customer_service', 'description' => 'Customer Service Representative'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}