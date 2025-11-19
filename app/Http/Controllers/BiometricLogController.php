<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBiometricLogRequest;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\BiometricLog;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BiometricLogController extends Controller
{
    private const TIME_IN_START_HOUR = 6;

    private const TIME_IN_END_HOUR = 12;

    private const TIME_OUT_START_HOUR = 12;

    private const TIME_OUT_START_MINUTE = 1;

    private const TIME_OUT_END_HOUR = 19;

    public function store(StoreBiometricLogRequest $request): JsonResponse
    {
        $data = $request->validated();

        $scanDateTime = Carbon::createFromFormat(
            'Y-m-d H:i',
            sprintf('%s %s', $data['scan_date'], $data['scan_time']),
            config('app.timezone')
        );

        $window = $this->resolveWindow($scanDateTime);

        if ($window === null) {
            return response()->json([
                'message' => 'Scan falls outside the supported time windows (06:00-12:00 for time in, 12:01-19:00 for time out).',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $employee = Employee::query()
            ->where('employee_code', $data['employee_code'])
            ->first();

        if (! $employee) {
            $log = BiometricLog::query()->create([
                'employee_code' => $data['employee_code'],
                'scan_time' => $scanDateTime,
            ]);

            return response()->json([
                'message' => 'Biometric log saved but no employee matched this code.',
                'biometric_log' => $this->transformBiometricLog($log),
            ], Response::HTTP_CREATED);
        }

        $attendanceSetting = AttendanceSetting::query()->latest()->first()
            ?? new AttendanceSetting(AttendanceSetting::defaultValues());

        $attendanceSetting->break_is_counted = (bool) $attendanceSetting->break_is_counted;

        $biometricLogData = null;
        $attendanceData = null;

        DB::transaction(function () use (
            &$biometricLogData,
            &$attendanceData,
            $data,
            $scanDateTime,
            $window,
            $employee,
            $attendanceSetting
        ) {
            $log = BiometricLog::query()->create([
                'employee_code' => $data['employee_code'],
                'scan_time' => $scanDateTime,
            ]);

            $attendance = Attendance::query()->firstOrNew([
                'employee_id' => $employee->id,
                'date' => $scanDateTime->toDateString(),
            ]);

            $attendance->employee()->associate($employee);

            // Pass the employee_code to applyScanToAttendance so it can query correctly
            $this->applyScanToAttendance($attendance, $scanDateTime, $window, $employee->employee_code);
            $this->updateAttendanceSummary($attendance, $attendanceSetting);

            $attendance->save();

            $biometricLogData = $this->transformBiometricLog($log);
            $attendanceData = $this->transformAttendance($attendance);
        });

        return response()->json([
            'message' => 'Biometric log saved and attendance updated.',
            'biometric_log' => $biometricLogData,
            'attendance' => $attendanceData,
        ], Response::HTTP_CREATED);
    }

    private function resolveWindow(Carbon $scanTime): ?string
    {
        $timeInStart = $scanTime->copy()->setTime(self::TIME_IN_START_HOUR, 0);
        $timeInEnd = $scanTime->copy()->setTime(self::TIME_IN_END_HOUR, 0);
        $timeOutStart = $scanTime->copy()->setTime(self::TIME_OUT_START_HOUR, self::TIME_OUT_START_MINUTE);
        $timeOutEnd = $scanTime->copy()->setTime(self::TIME_OUT_END_HOUR, 0);

        if ($scanTime->betweenIncluded($timeInStart, $timeInEnd)) {
            return 'in';
        }

        if ($scanTime->betweenIncluded($timeOutStart, $timeOutEnd)) {
            return 'out';
        }

        return null;
    }

    private function applyScanToAttendance(Attendance $attendance, Carbon $scanTime, string $window, string $employeeCode): void
    {
        if (! $attendance->exists) {
            $attendance->status = 'Incomplete';
            $attendance->remarks = 'Missing Time In & Time Out';
        }

        if ($window === 'in') {
            // Find the earliest scan from all biometric logs for this date (including the one just added)
            $timeInStart = $scanTime->copy()->setTime(self::TIME_IN_START_HOUR, 0);
            $timeInEnd = $scanTime->copy()->setTime(self::TIME_IN_END_HOUR, 0);

            $earliestBiometricScan = BiometricLog::query()
                ->where('employee_code', $employeeCode)
                ->whereDate('scan_time', $scanTime->toDateString())
                ->whereBetween('scan_time', [$timeInStart, $timeInEnd])
                ->orderBy('scan_time', 'asc')
                ->first();

            if ($earliestBiometricScan) {
                $earliestTime = $earliestBiometricScan->scan_time instanceof Carbon
                    ? $earliestBiometricScan->scan_time
                    : Carbon::parse($earliestBiometricScan->scan_time);

                // Only update if the earliest biometric scan is earlier than current attendance time_in
                // Or if attendance has no time_in
                // This respects manually added attendance if it's already the earliest
                if (! $attendance->time_in) {
                    $attendance->time_in = $earliestTime->format('H:i:s');
                } else {
                    // Compare time portions only
                    $earliestTimeOnly = Carbon::createFromFormat('H:i:s', $earliestTime->format('H:i:s'));
                    $currentTimeOnly = Carbon::createFromFormat('H:i:s', $attendance->time_in);

                    if ($earliestTimeOnly->lt($currentTimeOnly)) {
                        $attendance->time_in = $earliestTime->format('H:i:s');
                    }
                }
            }
        }

        if ($window === 'out') {
            // Find the latest scan from all biometric logs for this date (including the one just added)
            $timeOutStart = $scanTime->copy()->setTime(self::TIME_OUT_START_HOUR, self::TIME_OUT_START_MINUTE);
            $timeOutEnd = $scanTime->copy()->setTime(self::TIME_OUT_END_HOUR, 0);

            $latestBiometricScan = BiometricLog::query()
                ->where('employee_code', $employeeCode)
                ->whereDate('scan_time', $scanTime->toDateString())
                ->whereBetween('scan_time', [$timeOutStart, $timeOutEnd])
                ->orderBy('scan_time', 'desc')
                ->first();

            if ($latestBiometricScan) {
                $latestTime = $latestBiometricScan->scan_time instanceof Carbon
                    ? $latestBiometricScan->scan_time
                    : Carbon::parse($latestBiometricScan->scan_time);

                // Only update if the latest biometric scan is later than current attendance time_out
                // Or if attendance has no time_out
                // This respects manually added attendance if it's already the latest
                if (! $attendance->time_out) {
                    $attendance->time_out = $latestTime->format('H:i:s');
                } else {
                    // Compare time portions only
                    $latestTimeOnly = Carbon::createFromFormat('H:i:s', $latestTime->format('H:i:s'));
                    $currentTimeOnly = Carbon::createFromFormat('H:i:s', $attendance->time_out);

                    if ($latestTimeOnly->gt($currentTimeOnly)) {
                        $attendance->time_out = $latestTime->format('H:i:s');
                    }
                }
            }
        }
    }

    private function updateAttendanceSummary(Attendance $attendance, AttendanceSetting $settings): void
    {
        $timeIn = $attendance->time_in
            ? Carbon::createFromFormat('Y-m-d H:i:s', $attendance->date.' '.$attendance->time_in)
            : null;
        $timeOut = $attendance->time_out
            ? Carbon::createFromFormat('Y-m-d H:i:s', $attendance->date.' '.$attendance->time_out)
            : null;
        $requiredTimeIn = Carbon::createFromFormat('Y-m-d H:i', $attendance->date.' '.$settings->required_time_in);

        if ($timeIn && $timeOut) {
            $attendance->status = $timeIn->greaterThan($requiredTimeIn) ? 'Late' : 'Present';
            $attendance->remarks = 'Complete';
            $attendance->total_hours = $this->calculateTotalHours($timeIn, $timeOut, $requiredTimeIn, $settings);

            return;
        }

        if ($timeIn) {
            $attendance->status = 'Incomplete';
            $attendance->remarks = 'Missing Time Out';
            $attendance->total_hours = null;

            return;
        }

        if ($timeOut) {
            $attendance->status = 'Incomplete';
            $attendance->remarks = 'Missing Time In';
            $attendance->total_hours = null;

            return;
        }

        $attendance->status = 'Incomplete';
        $attendance->remarks = 'Missing Time In & Time Out';
        $attendance->total_hours = null;
    }

    private function calculateTotalHours(
        Carbon $timeIn,
        Carbon $timeOut,
        Carbon $requiredTimeIn,
        AttendanceSetting $settings
    ): float {
        $workStart = $timeIn->greaterThan($requiredTimeIn) ? $timeIn : $requiredTimeIn;
        $totalMinutes = max(0, $workStart->diffInMinutes($timeOut));

        if (! $settings->break_is_counted) {
            $totalMinutes = max(0, $totalMinutes - (int) $settings->break_duration_minutes);
        }

        return round($totalMinutes / 60, 2);
    }

    private function transformAttendance(Attendance $attendance): array
    {
        $employee = $attendance->employee ?? $attendance->loadMissing('employee')->employee;

        return [
            'id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'employee_code' => $employee?->employee_code,
            'name' => $employee?->full_name,
            'department' => $employee?->department?->name,
            'department_id' => $employee?->department_id,
            'position' => $employee?->position?->name,
            'position_id' => $employee?->position_id,
            'date' => $attendance->date,
            'time_in' => $attendance->time_in,
            'time_out' => $attendance->time_out,
            'total_hours' => $attendance->total_hours,
            'status' => $attendance->status,
            'remarks' => $attendance->remarks,
        ];
    }

    private function transformBiometricLog(BiometricLog $log): array
    {
        $scanTime = $log->scan_time instanceof Carbon
            ? $log->scan_time
            : Carbon::parse($log->scan_time);

        return [
            'id' => $log->id,
            'employee_code' => $log->employee_code,
            'date' => $scanTime->toDateString(),
            'time' => $scanTime->format('H:i'),
            'scan_time' => $scanTime->toDateTimeString(),
        ];
    }

    public function destroy(BiometricLog $biometricLog): JsonResponse
    {
        $scanTime = $biometricLog->scan_time instanceof Carbon
            ? $biometricLog->scan_time
            : Carbon::parse($biometricLog->scan_time);

        $window = $this->resolveWindow($scanTime);
        $attendanceData = null;

        DB::transaction(function () use ($biometricLog, $scanTime, $window, &$attendanceData) {
            // Find employee by employee_code
            $employee = Employee::query()
                ->where('employee_code', $biometricLog->employee_code)
                ->first();

            if ($employee && $window) {
                // Find attendance record for this employee and date
                $attendance = Attendance::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('date', $scanTime->toDateString())
                    ->first();

                if ($attendance) {
                    $attendanceTimeIn = $attendance->time_in
                        ? Carbon::createFromFormat('H:i:s', $attendance->time_in)
                        : null;
                    $attendanceTimeOut = $attendance->time_out
                        ? Carbon::createFromFormat('H:i:s', $attendance->time_out)
                        : null;

                    // Compare time portions only (HH:mm:ss)
                    $scanTimeFormatted = $scanTime->format('H:i:s');
                    $matchesTimeIn = $attendanceTimeIn && $scanTimeFormatted === $attendanceTimeIn->format('H:i:s');
                    $matchesTimeOut = $attendanceTimeOut && $scanTimeFormatted === $attendanceTimeOut->format('H:i:s');

                    if ($matchesTimeIn || $matchesTimeOut) {
                        // This scan is being used in attendance, need to check if we should update
                        $shouldUpdate = false;

                        if ($window === 'in' && $matchesTimeIn) {
                            // Find other scans in time_in window (06:00-12:00)
                            $timeInStart = $scanTime->copy()->setTime(self::TIME_IN_START_HOUR, 0);
                            $timeInEnd = $scanTime->copy()->setTime(self::TIME_IN_END_HOUR, 0);

                            $otherScan = BiometricLog::query()
                                ->where('employee_code', $biometricLog->employee_code)
                                ->whereDate('scan_time', $scanTime->toDateString())
                                ->where('id', '!=', $biometricLog->id)
                                ->whereBetween('scan_time', [$timeInStart, $timeInEnd])
                                ->orderBy('scan_time', 'asc')
                                ->first();

                            if ($otherScan) {
                                // Check if the remaining earliest scan is different from current attendance time
                                $earliestRemainingTime = $otherScan->scan_time instanceof Carbon
                                    ? $otherScan->scan_time
                                    : Carbon::parse($otherScan->scan_time);

                                // Only update if the remaining earliest is different from current attendance
                                // This means if manually added attendance is already the earliest, don't touch it
                                if ($earliestRemainingTime->format('H:i:s') !== $attendanceTimeIn->format('H:i:s')) {
                                    $attendance->time_in = $earliestRemainingTime->format('H:i:s');
                                    $shouldUpdate = true;
                                }
                            } else {
                                // No other biometric scans in this window
                                // Since the deleted scan matched the attendance time, set it to null
                                $attendance->time_in = null;
                                $shouldUpdate = true;
                            }
                        }

                        if ($window === 'out' && $matchesTimeOut) {
                            // Find other scans in time_out window (12:01-19:00)
                            $timeOutStart = $scanTime->copy()->setTime(self::TIME_OUT_START_HOUR, self::TIME_OUT_START_MINUTE);
                            $timeOutEnd = $scanTime->copy()->setTime(self::TIME_OUT_END_HOUR, 0);

                            $otherScan = BiometricLog::query()
                                ->where('employee_code', $biometricLog->employee_code)
                                ->whereDate('scan_time', $scanTime->toDateString())
                                ->where('id', '!=', $biometricLog->id)
                                ->whereBetween('scan_time', [$timeOutStart, $timeOutEnd])
                                ->orderBy('scan_time', 'desc')
                                ->first();

                            if ($otherScan) {
                                // Check if the remaining latest scan is different from current attendance time
                                $latestRemainingTime = $otherScan->scan_time instanceof Carbon
                                    ? $otherScan->scan_time
                                    : Carbon::parse($otherScan->scan_time);

                                // Only update if the remaining latest is different from current attendance
                                // This means if manually added attendance is already the latest, don't touch it
                                if ($latestRemainingTime->format('H:i:s') !== $attendanceTimeOut->format('H:i:s')) {
                                    $attendance->time_out = $latestRemainingTime->format('H:i:s');
                                    $shouldUpdate = true;
                                }
                            } else {
                                // No other biometric scans in this window
                                // Since the deleted scan matched the attendance time, set it to null
                                $attendance->time_out = null;
                                $shouldUpdate = true;
                            }
                        }

                        // Only recalculate and save if we actually updated something
                        if ($shouldUpdate) {
                            $attendanceSetting = AttendanceSetting::query()->latest()->first()
                                ?? new AttendanceSetting(AttendanceSetting::defaultValues());
                            $attendanceSetting->break_is_counted = (bool) $attendanceSetting->break_is_counted;

                            $this->updateAttendanceSummary($attendance, $attendanceSetting);
                            $attendance->save();

                            $attendanceData = $this->transformAttendance($attendance);
                        }
                    }
                }
            }

            // Delete the biometric log
            $biometricLog->delete();
        });

        $response = [
            'message' => 'Biometric log deleted successfully.',
        ];

        if ($attendanceData) {
            $response['attendance'] = $attendanceData;
        }

        return response()->json($response, Response::HTTP_OK);
    }
}
