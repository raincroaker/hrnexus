<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBiometricLogRequest;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\BiometricLog;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BiometricLogController extends Controller
{
    private const WINDOW_START_HOUR = 6;

    private const WINDOW_END_HOUR = 20;

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
                'message' => 'Scan falls outside the supported time window (06:00-20:00).',
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
            $this->applyScanToAttendance($attendance, $scanDateTime, $employee->employee_code);
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
        $windowStart = $scanTime->copy()->setTime(self::WINDOW_START_HOUR, 0);
        $windowEnd = $scanTime->copy()->setTime(self::WINDOW_END_HOUR, 0);

        if ($scanTime->betweenIncluded($windowStart, $windowEnd)) {
            return 'window';
        }

        return null;
    }

    private function applyScanToAttendance(Attendance $attendance, Carbon $scanTime, string $employeeCode): void
    {
        // Don't set status here - it will be determined in updateAttendanceSummary()

        $windowStart = $scanTime->copy()->setTime(self::WINDOW_START_HOUR, 0);
        $windowEnd = $scanTime->copy()->setTime(self::WINDOW_END_HOUR, 0);

        $earliestBiometricScan = BiometricLog::query()
            ->where('employee_code', $employeeCode)
            ->whereDate('scan_time', $scanTime->toDateString())
            ->whereBetween('scan_time', [$windowStart, $windowEnd])
            ->orderBy('scan_time', 'asc')
            ->first();

        $latestBiometricScan = BiometricLog::query()
            ->where('employee_code', $employeeCode)
            ->whereDate('scan_time', $scanTime->toDateString())
            ->whereBetween('scan_time', [$windowStart, $windowEnd])
            ->orderBy('scan_time', 'desc')
            ->first();

        if ($earliestBiometricScan) {
            $earliestTime = $earliestBiometricScan->scan_time instanceof Carbon
                ? $earliestBiometricScan->scan_time
                : Carbon::parse($earliestBiometricScan->scan_time);

            $attendance->time_in = $earliestTime->format('H:i:s');
        }

        if ($earliestBiometricScan && $latestBiometricScan) {
            $earliestTime = $earliestBiometricScan->scan_time instanceof Carbon
                ? $earliestBiometricScan->scan_time
                : Carbon::parse($earliestBiometricScan->scan_time);
            $latestTime = $latestBiometricScan->scan_time instanceof Carbon
                ? $latestBiometricScan->scan_time
                : Carbon::parse($latestBiometricScan->scan_time);

            if ($latestTime->gt($earliestTime)) {
                $attendance->time_out = $latestTime->format('H:i:s');
            } else {
                $attendance->time_out = null;
            }
        } else {
            $attendance->time_out = null;
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
            // Only time_in exists - determine if Present or Late based on time_in
            $attendance->status = $timeIn->greaterThan($requiredTimeIn) ? 'Late' : 'Present';
            $attendance->remarks = 'Missing Time Out';
            $attendance->total_hours = null;

            return;
        }

        if ($timeOut) {
            // Only time_out exists - can't determine if late without time_in, default to Present
            $attendance->status = 'Present';
            $attendance->remarks = 'Missing Time In';
            $attendance->total_hours = null;

            return;
        }

        // Neither time_in nor time_out exists - check leave first, else Absent
        $employeeLeave = EmployeeLeave::query()
            ->where('employee_id', $attendance->employee_id)
            ->whereDate('date', $attendance->date)
            ->first();

        if ($employeeLeave) {
            $attendance->status = 'Leave';
            $attendance->remarks = 'On leave';
            $attendance->employee_leave_id = $employeeLeave->id;
        } else {
            $attendance->status = 'Absent';
            $attendance->remarks = 'Missing Time In & Time Out';
        }
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

        $attendanceData = null;

        $window = $this->resolveWindow($scanTime);

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
                    if ($window === 'window') {
                        $windowStart = $scanTime->copy()->setTime(self::WINDOW_START_HOUR, 0);
                        $windowEnd = $scanTime->copy()->setTime(self::WINDOW_END_HOUR, 0);

                        $remainingScans = BiometricLog::query()
                            ->where('employee_code', $biometricLog->employee_code)
                            ->whereDate('scan_time', $scanTime->toDateString())
                            ->where('id', '!=', $biometricLog->id)
                            ->whereBetween('scan_time', [$windowStart, $windowEnd])
                            ->orderBy('scan_time')
                            ->get();

                        if ($remainingScans->isNotEmpty()) {
                            $earliest = $remainingScans->first()->scan_time instanceof Carbon
                                ? $remainingScans->first()->scan_time
                                : Carbon::parse($remainingScans->first()->scan_time);
                            $latest = $remainingScans->last()->scan_time instanceof Carbon
                                ? $remainingScans->last()->scan_time
                                : Carbon::parse($remainingScans->last()->scan_time);

                            $attendance->time_in = $earliest->format('H:i:s');
                            $attendance->time_out = $latest->gt($earliest)
                                ? $latest->format('H:i:s')
                                : null;
                        } else {
                            $attendance->time_in = null;
                            $attendance->time_out = null;
                        }

                        $attendanceSetting = AttendanceSetting::query()->latest()->first()
                            ?? new AttendanceSetting(AttendanceSetting::defaultValues());
                        $attendanceSetting->break_is_counted = (bool) $attendanceSetting->break_is_counted;

                        $this->updateAttendanceSummary($attendance, $attendanceSetting);
                        $attendance->save();
                        $attendanceData = $this->transformAttendance($attendance);
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
