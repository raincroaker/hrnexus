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

        // Use Nov 24, 2025 as today's date
        $today = Carbon::create(2025, 11, 24)->startOfDay();
        $dates = $this->generateDateRange(5, $today);
        $requiredIn = Carbon::parse($setting->required_time_in);
        $requiredOut = Carbon::parse($setting->required_time_out);

        foreach ($employees as $employee) {
            foreach ($dates as $date) {
                $isToday = $date->isSameDay($today);
                $scenarioRoll = rand(1, 100);
                $timeIn = null;
                $timeOut = null;
                $hasTimeIn = false;
                $hasTimeOut = false;
                $status = 'Incomplete';
                $remarks = 'Missing Time In & Time Out';

                // For today's date: all employees should have time_in but no time_out
                if ($isToday) {
                    $hasTimeIn = true;
                    $hasTimeOut = false;
                    // Mix of on-time and late arrivals
                    $timeInOffset = rand(1, 100) <= 70 ? rand(-10, 10) : rand(15, 45); // 70% on-time, 30% late
                    $timeIn = (clone $requiredIn)->setDateFrom($date)->addMinutes($timeInOffset);
                    $status = $timeIn->greaterThan((clone $requiredIn)->setDateFrom($date)->addMinutes(15)) ? 'Late' : 'Incomplete';
                    $remarks = 'Missing Time Out';
                } else {
                    // For past dates: normal scenarios
                    $timeIn = (clone $requiredIn)->setDateFrom($date)->addMinutes(rand(-20, 40));
                    $timeOut = (clone $requiredOut)->setDateFrom($date)->subMinutes(rand(0, 30));
                    $hasTimeIn = true;
                    $hasTimeOut = true;

                    if ($scenarioRoll <= 15) {
                        $hasTimeIn = false;
                        $status = 'Incomplete';
                        $remarks = 'Missing Time In';
                    } elseif ($scenarioRoll <= 30) {
                        $hasTimeOut = false;
                        $status = 'Incomplete';
                        $remarks = 'Missing Time Out';
                    } else {
                        if ($timeIn->greaterThan((clone $requiredIn)->setDateFrom($date)->addMinutes(15))) {
                            $status = 'Late';
                        } else {
                            $status = 'Present';
                        }
                        $remarks = 'Complete';
                    }

                    if (! $hasTimeIn) {
                        $timeIn = null;
                    }

                    if (! $hasTimeOut) {
                        $timeOut = null;
                    }
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
    private function generateDateRange(int $days, Carbon $baseDate): Collection
    {
        return collect(range(0, $days - 1))
            ->map(fn (int $offset) => (clone $baseDate)->subDays($offset)->startOfDay());
    }
}
