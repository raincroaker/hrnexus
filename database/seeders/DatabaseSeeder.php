<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Faker\Factory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    private int $employeeCounter = 1;

    private $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    public function run(): void
    {
        // 1️⃣ Seed departments and positions first
        $this->call([
            DepartmentSeeder::class,
            PositionSeeder::class,
        ]);

        // 2️⃣ Create Admin User (only 1 admin, from HR department)
        $hrDepartment = Department::where('code', 'HR')->first();
        $hrPositions = $hrDepartment?->positions;
        $adminPosition = $hrPositions?->where('name', 'like', '%Manager%')->first() ?? $hrPositions?->first();

        $this->createEmployee([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@hrnexus.com',
            'role' => 'admin',
            'department_id' => $hrDepartment?->id,
            'position_id' => $adminPosition?->id,
        ]);

        // 3️⃣ Create employees for each department
        $departments = Department::all();

        foreach ($departments as $department) {
            $positions = $department->positions;

            if ($positions->isEmpty()) {
                $this->command->warn("No positions found for department {$department->name}. Skipping.");

                continue;
            }

            // Create 1 department manager per department
            $managerPosition = $positions->where('name', 'like', '%Manager%')->first() ?? $positions->first();
            $managerFirstName = $this->faker->firstName();
            $managerLastName = $this->faker->lastName();
            $this->createEmployee([
                'first_name' => $managerFirstName,
                'last_name' => $managerLastName,
                'email' => strtolower($department->code).'.manager@hrnexus.com',
                'role' => 'department_manager',
                'department_id' => $department->id,
                'position_id' => $managerPosition->id,
            ]);

            // Create 5+ employees per department
            $employeeCount = 5;
            for ($i = 0; $i < $employeeCount; $i++) {
                // Randomly assign a position (can duplicate)
                $randomPosition = $positions->random();
                $employeeFirstName = $this->faker->firstName();
                $employeeLastName = $this->faker->lastName();

                $this->createEmployee([
                    'first_name' => $employeeFirstName,
                    'last_name' => $employeeLastName,
                    'email' => strtolower($employeeLastName).'.'.strtolower($employeeFirstName).'@hrnexus.com',
                    'role' => 'employee',
                    'department_id' => $department->id,
                    'position_id' => $randomPosition->id,
                ]);
            }
        }

        // 4️⃣ Seed attendance configuration and records
        $this->call([
            LeaveTypeSeeder::class,
            HolidaySeeder::class,
            AttendanceSettingSeeder::class,
            EmployeeLeavesSeeder::class,
            AttendanceSeeder::class,
        ]);

        // 5️⃣ Seed calendar events
        $this->call([
            EventCategorySeeder::class,
            CalendarEventSeeder::class,
            EventAttendeeSeeder::class,
            ChatSeeder::class,
        ]);

        // 6️⃣ Seed tags only (documents are not seeded)
        $this->call([
            TagSeeder::class,
        ]);

        $this->command->info('Users, attendance settings, attendance records, biometric logs, calendar events, and tags seeded successfully!');
    }

    private function createEmployee(array $data): void
    {
        $employeeCode = 'EMP'.str_pad((string) $this->employeeCounter++, 4, '0', STR_PAD_LEFT);

        $employee = Employee::create([
            'employee_code' => $employeeCode,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'department_id' => $data['department_id'],
            'position_id' => $data['position_id'],
            'contact_number' => $this->faker->phoneNumber(),
            'birth_date' => $this->faker->date('Y-m-d', '-25 years'),
            'avatar' => null,
        ]);

        User::factory()->create([
            'name' => $data['first_name'].' '.$data['last_name'],
            'email' => $data['email'],
            'password' => bcrypt('password'), // default password
        ]);
    }
}
