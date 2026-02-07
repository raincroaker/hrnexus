<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\BiometricLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BiometricLogSeeder extends Seeder
{
    public function run(): void
    {
        $attendances = Attendance::query()->with('employee')->get();

        if ($attendances->isEmpty()) {
            $this->command?->warn('No attendance records found. Skipping biometric log seeding.');

            return;
        }

        $employeeCodes = collect();

        foreach ($attendances as $attendance) {
            $employee = $attendance->employee;

            if (! $employee) {
                continue;
            }

            $employeeCodes->push($employee->employee_code);

            // Only seed logs for Present/Late (skip Absent/Leave/Holiday)
            if (in_array($attendance->status, ['Present', 'Late'], true)) {
                if ($attendance->time_in) {
                    $this->createBiometricLog(
                        $employee->employee_code,
                        Carbon::parse($attendance->date.' '.$attendance->time_in)
                    );
                }

                if ($attendance->time_out) {
                    $this->createBiometricLog(
                        $employee->employee_code,
                        Carbon::parse($attendance->date.' '.$attendance->time_out)
                    );
                }
            }
        }

        // Add extra biometric-only employees (on-time to avoid artificial lates)
        $this->seedSyntheticEmployeeCodes($employeeCodes->unique()->values(), 5);
    }

    private function createBiometricLog(string $employeeCode, Carbon $scanTime): void
    {
        BiometricLog::query()->firstOrCreate(
            [
                'employee_code' => $employeeCode,
                'scan_time' => $scanTime,
            ],
            []
        );
    }

    private function seedSyntheticEmployeeCodes(\Illuminate\Support\Collection $existingCodes, int $additionalCount): void
    {
        $nextNumber = $this->resolveNextEmployeeNumber($existingCodes);
        $start = Carbon::create(2026, 1, 1);
        $end = Carbon::create(2026, 2, 2);

        $dates = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dates[] = $cursor->copy();
            $cursor->addDay();
        }

        for ($i = 0; $i < $additionalCount; $i++) {
            $code = 'EMP'.str_pad((string) $nextNumber++, 4, '0', STR_PAD_LEFT);

            foreach ($dates as $date) {
                $timeIn = $date->copy()->setTime(8, 0);
                $timeOut = $date->copy()->setTime(17, 0);

                $this->createBiometricLog($code, $timeIn);
                $this->createBiometricLog($code, $timeOut);
            }
        }
    }

    private function resolveNextEmployeeNumber(\Illuminate\Support\Collection $codes): int
    {
        if ($codes->isEmpty()) {
            return 1;
        }

        $max = $codes->map(function (string $code): int {
            return (int) preg_replace('/[^0-9]/', '', $code) ?: 0;
        })->max();

        return $max + 1;
    }
}
