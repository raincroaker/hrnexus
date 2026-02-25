<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DeploymentBaseSeeder extends Seeder
{
    /**
     * Seed only deployment essentials:
     * - default departments
     * - default positions
     * - one default admin account
     *
     * Run with:
     * php artisan db:seed --class=DeploymentBaseSeeder
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            PositionSeeder::class,
            HolidaySeeder::class,
        ]);

        $email = 'admin@hrnexus.com';
        $password = 'password';

        $hrDepartment = Department::query()->where('code', 'HR')->first();
        $adminPosition = null;

        if ($hrDepartment) {
            $adminPosition = Position::query()
                ->where('department_id', $hrDepartment->id)
                ->where('name', 'HR Manager')
                ->first();
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin User',
                'password' => Hash::make($password),
            ]
        );

        Employee::query()->updateOrCreate(
            ['email' => $email],
            [
                'employee_code' => 'ADMIN001',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'role' => 'admin',
                'department_id' => $hrDepartment?->id,
                'position_id' => $adminPosition?->id,
                'contact_number' => null,
                'birth_date' => null,
                'avatar' => null,
            ]
        );

        $this->command?->info('Deployment base seeding complete.');
        $this->command?->info("Admin account: {$email} / {$password}");
    }
}
