<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Admin', 'Department Manager', 'Employee'];

        foreach ($roles as $roleName) {
            Role::updateOrCreate(['name' => $roleName]);
        }

        $this->command->info('Roles seeded successfully!');
    }
}
