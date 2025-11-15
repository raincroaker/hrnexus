<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1️⃣ Seed roles
        $this->call([
            RoleSeeder::class,
        ]);

        // 2️⃣ Define accounts
        $accounts = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => 'Admin',
                'employee_code' => 'EMP0001',
            ],
            [
                'name' => 'Manager User',
                'email' => 'manager@example.com',
                'role' => 'Department Manager',
                'employee_code' => 'EMP0002',
            ],
            [
                'name' => 'Employee User',
                'email' => 'employee@example.com',
                'role' => 'Employee',
                'employee_code' => 'EMP0003',
            ],
        ];

        foreach ($accounts as $data) {
            // 2a. Create employee first
            $employee = Employee::create([
                'department_id' => null,
                'position_id' => null,
                'employee_code' => $data['employee_code'],
                'first_name' => explode(' ', $data['name'])[0],
                'last_name' => explode(' ', $data['name'])[1] ?? '',
                'email' => $data['email'],
                'contact_number' => null,
                'birth_date' => null,
                'avatar' => null,
            ]);

            // 2b. Create user corresponding to employee
            $user = User::factory()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt('password'), // default password
            ]);

            // 2c. Attach role
            $role = Role::where('name', $data['role'])->first();
            $user->roles()->attach($role);
        }

        $this->command->info('Roles, users, and employees seeded successfully!');
    }
}
