<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminOnlySeeder extends Seeder
{
    /**
     * Seeds only the admin account for hosting.
     * Run with: php artisan db:seed --class=AdminOnlySeeder
     *
     * Credentials: admin@email.com / 123
     */
    public function run(): void
    {
        $email = 'admin@email.com';
        $password = '123';

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin User',
                'password' => Hash::make($password),
            ]
        );

        if ($user->wasRecentlyCreated) {
            $this->command->info('Admin user created: ' . $email);
        } else {
            $user->update(['password' => Hash::make($password)]);
            $this->command->info('Admin user already exists; password updated.');
        }

        $employee = Employee::query()->firstOrCreate(
            ['email' => $email],
            [
                'employee_code' => 'ADMIN001',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'role' => 'admin',
                'department_id' => null,
                'position_id' => null,
                'contact_number' => null,
                'birth_date' => null,
                'avatar' => null,
            ]
        );

        if ($employee->wasRecentlyCreated) {
            $this->command->info('Admin employee record created.');
        } else {
            $employee->update(['role' => 'admin']);
            $this->command->info('Admin employee record already exists; role set to admin.');
        }

        $this->command->info('Admin account ready. Login with: ' . $email . ' / ' . $password);
    }
}
