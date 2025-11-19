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

            if ($attendance->time_in && $attendance->time_out) {
                $timeIn = Carbon::parse($attendance->date.' '.$attendance->time_in);
                $timeOut = Carbon::parse($attendance->date.' '.$attendance->time_out);
                $midPoint = $timeIn->copy()->addMinutes(
                    intdiv($timeIn->diffInMinutes($timeOut), 2)
                );

                $this->createBiometricLog($employee->employee_code, $midPoint);
            }
        }

        $this->seedSyntheticEmployeeCodes($employeeCodes->unique()->values(), 10);
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
        $dates = collect(range(0, 4))->map(fn (int $offset) => now()->subDays($offset)->startOfDay());

        for ($i = 0; $i < $additionalCount; $i++) {
            $code = 'EMP'.str_pad((string) $nextNumber++, 4, '0', STR_PAD_LEFT);

            foreach ($dates as $date) {
                $scenarioRoll = rand(1, 100);
                $hasTimeIn = true;
                $hasTimeOut = true;

                if ($scenarioRoll <= 15) {
                    $hasTimeIn = false;
                } elseif ($scenarioRoll <= 30) {
                    $hasTimeOut = false;
                }

                $timeIn = $date->copy()->setTime(8, rand(0, 30));
                $timeOut = $date->copy()->setTime(17, rand(0, 30));

                if ($hasTimeIn) {
                    $this->createBiometricLog($code, $timeIn);
                }

                if ($hasTimeIn && $hasTimeOut && rand(0, 1)) {
                    $midPoint = $timeIn->copy()->addMinutes(intdiv($timeIn->diffInMinutes($timeOut), 2));
                    $this->createBiometricLog($code, $midPoint);
                }

                if ($hasTimeOut) {
                    $this->createBiometricLog($code, $timeOut);
                }
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
