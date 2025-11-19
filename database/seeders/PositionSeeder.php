<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            'HR' => [
                'HR Manager',
                'HR Specialist',
                'Recruitment Officer',
                'Training Coordinator',
                'Payroll Administrator',
            ],
            'IT' => [
                'IT Manager',
                'Senior Developer',
                'Junior Developer',
                'System Administrator',
                'IT Support Specialist',
            ],
            'FIN' => [
                'Finance Manager',
                'Senior Accountant',
                'Junior Accountant',
                'Financial Analyst',
                'Accounts Payable Clerk',
            ],
            'OPS' => [
                'Operations Manager',
                'Operations Coordinator',
                'Logistics Specialist',
                'Quality Assurance Officer',
            ],
        ];

        foreach ($positions as $departmentCode => $positionNames) {
            $department = Department::where('code', $departmentCode)->first();

            if (! $department) {
                $this->command->warn("Department {$departmentCode} not found. Skipping positions.");

                continue;
            }

            foreach ($positionNames as $positionName) {
                Position::updateOrCreate(
                    [
                        'department_id' => $department->id,
                        'name' => $positionName,
                    ]
                );
            }
        }

        $this->command->info('Positions seeded successfully!');
    }
}
