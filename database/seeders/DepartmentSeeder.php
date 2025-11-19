<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            [
                'code' => 'HR',
                'name' => 'Human Resources',
            ],
            [
                'code' => 'IT',
                'name' => 'Information Technology',
            ],
            [
                'code' => 'FIN',
                'name' => 'Finance',
            ],
            [
                'code' => 'OPS',
                'name' => 'Operations',
            ],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                $department
            );
        }

        $this->command->info('Departments seeded successfully!');
    }
}
