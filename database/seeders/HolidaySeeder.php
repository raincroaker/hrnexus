<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $holidays = [
            // Existing 2025 holidays
            ['name' => 'All Saints Day', 'date' => '2025-11-01'],
            ['name' => 'Christmas Day', 'date' => '2025-12-25'],
            // 2026 holidays used in Attendance/DTR (ensure New Year appears correctly)
            ['name' => "New Year's Day", 'date' => '2026-01-01'],
        ];

        foreach ($holidays as $holiday) {
            Holiday::query()->updateOrCreate(
                ['date' => $holiday['date']],
                ['name' => $holiday['name']]
            );
        }
    }
}
