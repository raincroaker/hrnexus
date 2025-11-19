<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $setting = AttendanceSetting::query()->first() ?? AttendanceSetting::query()->create([
            'required_time_in' => AttendanceSetting::DEFAULT_TIME_IN,
            'required_time_out' => AttendanceSetting::DEFAULT_TIME_OUT,
            'break_duration_minutes' => 60,
            'break_is_counted' => false,
        ]);

        $employees = Employee::query()->get();

        if ($employees->isEmpty()) {
            $this->command?->warn('No employees found. Skipping attendance seeding.');

            return;
        }

        $dates = $this->generateDateRange(5);
        $requiredIn = Carbon::parse($setting->required_time_in);
        $requiredOut = Carbon::parse($setting->required_time_out);

        foreach ($employees as $employee) {
            foreach ($dates as $date) {
                $scenarioRoll = rand(1, 100);
                $timeIn = (clone $requiredIn)->addMinutes(rand(-20, 40));
                $timeOut = (clone $requiredOut)->subMinutes(rand(0, 30));
                $hasTimeIn = true;
                $hasTimeOut = true;
                $status = 'Present';
                $remarks = 'Complete';

                if ($scenarioRoll <= 15) {
                    $hasTimeIn = false;
                    $status = 'Incomplete';
                    $remarks = 'Missing Time In';
                } elseif ($scenarioRoll <= 30) {
                    $hasTimeOut = false;
                    $status = 'Incomplete';
                    $remarks = 'Missing Time Out';
                } else {
                    if ($timeIn->greaterThan((clone $requiredIn)->addMinutes(15))) {
                        $status = 'Late';
                    }
                }

                if (! $hasTimeIn) {
                    $timeIn = null;
                }

                if (! $hasTimeOut) {
                    $timeOut = null;
                }

                $totalHours = null;

                if ($timeIn && $timeOut) {
                    $totalMinutes = $timeIn->diffInMinutes($timeOut);

                    if (! $setting->break_is_counted) {
                        $totalMinutes = max(0, $totalMinutes - $setting->break_duration_minutes);
                    }

                    $totalHours = round($totalMinutes / 60, 2);
                }

                Attendance::query()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'date' => $date->toDateString(),
                    ],
                    [
                        'time_in' => $timeIn?->format('H:i:s'),
                        'time_out' => $timeOut?->format('H:i:s'),
                        'total_hours' => $totalHours,
                        'status' => $status,
                        'remarks' => $remarks,
                    ]
                );
            }
        }
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function generateDateRange(int $days): Collection
    {
        return collect(range(0, $days - 1))
            ->map(fn (int $offset) => now()->subDays($offset)->startOfDay());
    }
}
