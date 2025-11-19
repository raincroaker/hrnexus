<?php

namespace Database\Seeders;

use App\Models\AttendanceSetting;
use Illuminate\Database\Seeder;

class AttendanceSettingSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'required_time_in' => '08:00',
            'required_time_out' => '17:00',
            'break_duration_minutes' => 60,
            'break_is_counted' => false,
        ];

        $setting = AttendanceSetting::query()->first();

        if ($setting) {
            $setting->update($data);

            return;
        }

        AttendanceSetting::query()->create($data);
    }
}
