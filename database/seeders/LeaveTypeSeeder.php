<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'SL', 'annual_leaves' => 12],
            ['name' => 'VL', 'annual_leaves' => 12],
            ['name' => 'EL', 'annual_leaves' => 12],
        ];

        foreach ($types as $type) {
            LeaveType::query()->updateOrCreate(
                ['name' => $type['name']],
                ['annual_leaves' => $type['annual_leaves']]
            );
        }
    }
}
