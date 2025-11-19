<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\SyncAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\BiometricLog;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        $currentEmployee = Employee::query()
            ->with(['department', 'position'])
            ->where('email', $user->email)
            ->firstOrFail();

        $role = $currentEmployee->role;
        $canManageAll = $role === 'admin';

        $attendanceSettingsModel = AttendanceSetting::query()->latest()->first();
        $resolvedAttendanceSettings = $attendanceSettingsModel
            ? $this->transformAttendanceSettings($attendanceSettingsModel)
            : $this->transformAttendanceSettings(new AttendanceSetting(AttendanceSetting::defaultValues()));

        $pageProps = [
            'currentUser' => $this->transformEmployee($currentEmployee),
            'attendanceSettings' => $resolvedAttendanceSettings,
            'myAttendance' => $this->attendanceCollection($currentEmployee->id),
            'canManageAll' => $canManageAll,
        ];

        if ($canManageAll) {
            $pageProps['teamAttendance'] = $this->attendanceCollection();
            $pageProps['employees'] = $this->employeesCollection();
            $pageProps['biometricLogs'] = $this->biometricLogCollection();
        }

        return Inertia::render('Attendance', $pageProps);
    }

    private function attendanceCollection(?int $employeeId = null)
    {
        $query = Attendance::query()
            ->with(['employee.department', 'employee.position'])
            ->orderByDesc('date');

        $query->when(
            $employeeId,
            fn ($builder) => $builder->where('employee_id', $employeeId)->limit(30),
            fn ($builder) => $builder->limit(200)
        );

        return $query->get()->map(fn (Attendance $attendance) => $this->transformAttendance($attendance));
    }

    private function employeesCollection()
    {
        return Employee::query()
            ->with(['department', 'position'])
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $employee) => $this->transformEmployee($employee));
    }

    private function biometricLogCollection()
    {
        return BiometricLog::query()
            ->orderByDesc('scan_time')
            ->limit(200)
            ->get()
            ->map(fn (BiometricLog $log) => $this->transformBiometricLog($log));
    }

    private function transformAttendance(Attendance $attendance): array
    {
        $employee = $attendance->employee;
        $date = $attendance->date instanceof Carbon ? $attendance->date->toDateString() : $attendance->date;

        return [
            'id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'employee_code' => $employee?->employee_code,
            'name' => $employee?->full_name,
            'department' => $employee?->department?->name,
            'department_id' => $employee?->department_id,
            'position' => $employee?->position?->name,
            'position_id' => $employee?->position_id,
            'date' => $date,
            'time_in' => $attendance->time_in,
            'time_out' => $attendance->time_out,
            'total_hours' => $attendance->total_hours,
            'status' => $attendance->status,
            'remarks' => $attendance->remarks,
        ];
    }

    private function transformEmployee(Employee $employee): array
    {
        $birthDate = $employee->birth_date instanceof Carbon ? $employee->birth_date->format('Y-m-d') : $employee->birth_date;
        $avatarUrl = $employee->avatar ? Storage::url($employee->avatar) : null;

        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => $employee->full_name,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'department' => $employee->department?->name ?? '',
            'department_id' => $employee->department_id,
            'position' => $employee->position?->name ?? '',
            'position_id' => $employee->position_id,
            'role' => $employee->role,
            'email' => $employee->email,
            'contact_number' => $employee->contact_number,
            'birth_date' => $birthDate,
            'avatar' => $avatarUrl,
        ];
    }

    private function transformBiometricLog(BiometricLog $log): array
    {
        $scanTime = $log->scan_time instanceof Carbon ? $log->scan_time : Carbon::parse($log->scan_time);

        return [
            'id' => $log->id,
            'employee_code' => $log->employee_code,
            'date' => $scanTime->toDateString(),
            'time' => $scanTime->format('H:i:s'),
            'scan_time' => $scanTime->toDateTimeString(),
        ];
    }

    private function transformAttendanceSettings(AttendanceSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'required_time_in' => $setting->required_time_in,
            'required_time_out' => $setting->required_time_out,
            'break_duration_minutes' => $setting->break_duration_minutes,
            'break_is_counted' => (bool) $setting->break_is_counted,
        ];
    }

    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $date = Carbon::createFromFormat('Y-m-d', $data['date'], config('app.timezone'));

        $attendanceSetting = AttendanceSetting::query()->latest()->first()
            ?? new AttendanceSetting(AttendanceSetting::defaultValues());

        $attendanceSetting->break_is_counted = (bool) $attendanceSetting->break_is_counted;

        $attendanceData = null;

        DB::transaction(function () use (
            &$attendanceData,
            $data,
            $employee,
            $date,
            $attendanceSetting
        ) {
            // Create or update attendance record
            $attendance = Attendance::query()->firstOrNew([
                'employee_id' => $employee->id,
                'date' => $date->toDateString(),
            ]);

            $attendance->employee()->associate($employee);

            if (! empty($data['time_in'])) {
                $attendance->time_in = $data['time_in'].':00';
            }

            if (! empty($data['time_out'])) {
                $attendance->time_out = $data['time_out'].':00';
            }

            // Calculate status and total hours
            $this->updateAttendanceSummary($attendance, $attendanceSetting);
            $attendance->save();

            $attendanceData = $this->transformAttendance($attendance);
        });

        return response()->json([
            'message' => 'Attendance record created successfully.',
            'attendance' => $attendanceData,
        ], \Illuminate\Http\Response::HTTP_CREATED);
    }

    public function update(UpdateAttendanceRequest $request, Attendance $attendance): JsonResponse
    {
        $data = $request->validated();
        $employee = $attendance->employee;
        $date = Carbon::createFromFormat('Y-m-d', $attendance->date, config('app.timezone'));

        $attendanceSetting = AttendanceSetting::query()->latest()->first()
            ?? new AttendanceSetting(AttendanceSetting::defaultValues());

        $attendanceSetting->break_is_counted = (bool) $attendanceSetting->break_is_counted;

        $attendanceData = null;

        DB::transaction(function () use (
            &$attendanceData,
            $data,
            $attendance,
            $attendanceSetting
        ) {
            // Update attendance record
            if (isset($data['time_in'])) {
                $attendance->time_in = $data['time_in'] ? $data['time_in'].':00' : null;
            }

            if (isset($data['time_out'])) {
                $attendance->time_out = $data['time_out'] ? $data['time_out'].':00' : null;
            }

            // Recalculate status and total hours
            $this->updateAttendanceSummary($attendance, $attendanceSetting);
            $attendance->save();

            $attendanceData = $this->transformAttendance($attendance);
        });

        return response()->json([
            'message' => 'Attendance record updated successfully.',
            'attendance' => $attendanceData,
        ], \Illuminate\Http\Response::HTTP_OK);
    }

    public function destroy(Attendance $attendance): JsonResponse
    {
        $attendance->delete();

        return response()->json([
            'message' => 'Attendance record deleted successfully.',
        ], \Illuminate\Http\Response::HTTP_OK);
    }

    public function sync(SyncAttendanceRequest $request): JsonResponse
    {
        $attendanceSetting = AttendanceSetting::query()->latest()->first()
            ?? new AttendanceSetting(AttendanceSetting::defaultValues());

        $attendanceSetting->break_is_counted = (bool) $attendanceSetting->break_is_counted;

        $createdCount = 0;
        $updatedCount = 0;
        $deletedCount = 0;

        DB::transaction(function () use ($attendanceSetting, &$createdCount, &$updatedCount, &$deletedCount) {
            // Get all biometric logs grouped by employee_code and date
            $biometricLogs = BiometricLog::query()
                ->orderBy('scan_time', 'asc')
                ->get();

            // Group logs by employee_code and date
            $groupedLogs = [];
            foreach ($biometricLogs as $log) {
                $scanTime = $log->scan_time instanceof Carbon
                    ? $log->scan_time
                    : Carbon::parse($log->scan_time);

                $key = $log->employee_code.'|'.$scanTime->toDateString();
                if (! isset($groupedLogs[$key])) {
                    $groupedLogs[$key] = [
                        'employee_code' => $log->employee_code,
                        'date' => $scanTime->toDateString(),
                        'logs' => [],
                    ];
                }
                $groupedLogs[$key]['logs'][] = $scanTime;
            }

            // Process each group
            foreach ($groupedLogs as $group) {
                $employee = Employee::query()
                    ->where('employee_code', $group['employee_code'])
                    ->first();

                if ($employee) {
                    // Employee exists, check if attendance exists
                    $attendance = Attendance::query()
                        ->where('employee_id', $employee->id)
                        ->whereDate('date', $group['date'])
                        ->first();

                    if (! $attendance) {
                        // Attendance doesn't exist, create it
                        $attendance = new Attendance;
                        $attendance->employee_id = $employee->id;
                        $attendance->date = $group['date'];

                        // Find earliest time_in (06:00-12:00) and latest time_out (12:01-19:00)
                        $timeInStart = Carbon::createFromFormat('Y-m-d H:i:s', $group['date'].' 06:00:00');
                        $timeInEnd = Carbon::createFromFormat('Y-m-d H:i:s', $group['date'].' 12:00:00');
                        $timeOutStart = Carbon::createFromFormat('Y-m-d H:i:s', $group['date'].' 12:01:00');
                        $timeOutEnd = Carbon::createFromFormat('Y-m-d H:i:s', $group['date'].' 19:00:00');

                        $earliestTimeIn = null;
                        $latestTimeOut = null;

                        foreach ($group['logs'] as $scanTime) {
                            if ($scanTime->betweenIncluded($timeInStart, $timeInEnd)) {
                                if (! $earliestTimeIn || $scanTime->lt($earliestTimeIn)) {
                                    $earliestTimeIn = $scanTime;
                                }
                            }

                            if ($scanTime->betweenIncluded($timeOutStart, $timeOutEnd)) {
                                if (! $latestTimeOut || $scanTime->gt($latestTimeOut)) {
                                    $latestTimeOut = $scanTime;
                                }
                            }
                        }

                        if ($earliestTimeIn) {
                            $attendance->time_in = $earliestTimeIn->format('H:i:s');
                        }

                        if ($latestTimeOut) {
                            $attendance->time_out = $latestTimeOut->format('H:i:s');
                        }

                        $this->updateAttendanceSummary($attendance, $attendanceSetting);
                        $attendance->save();
                        $createdCount++;
                    } else {
                        // Attendance exists, update time_in and time_out from biometric logs if needed
                        $timeInStart = Carbon::createFromFormat('Y-m-d H:i:s', $group['date'].' 06:00:00');
                        $timeInEnd = Carbon::createFromFormat('Y-m-d H:i:s', $group['date'].' 12:00:00');
                        $timeOutStart = Carbon::createFromFormat('Y-m-d H:i:s', $group['date'].' 12:01:00');
                        $timeOutEnd = Carbon::createFromFormat('Y-m-d H:i:s', $group['date'].' 19:00:00');

                        $earliestTimeIn = null;
                        $latestTimeOut = null;

                        foreach ($group['logs'] as $scanTime) {
                            if ($scanTime->betweenIncluded($timeInStart, $timeInEnd)) {
                                if (! $earliestTimeIn || $scanTime->lt($earliestTimeIn)) {
                                    $earliestTimeIn = $scanTime;
                                }
                            }

                            if ($scanTime->betweenIncluded($timeOutStart, $timeOutEnd)) {
                                if (! $latestTimeOut || $scanTime->gt($latestTimeOut)) {
                                    $latestTimeOut = $scanTime;
                                }
                            }
                        }

                        // Update time_in if biometric has earlier time
                        if ($earliestTimeIn) {
                            $earliestTimeInFormatted = $earliestTimeIn->format('H:i:s');
                            $currentTimeInFormatted = $attendance->time_in;

                            // Only update if attendance has no time_in or biometric earliest is earlier
                            if (! $currentTimeInFormatted) {
                                $attendance->time_in = $earliestTimeInFormatted;
                            } else {
                                // Compare time portions only
                                $earliestTimeOnly = Carbon::createFromFormat('H:i:s', $earliestTimeInFormatted);
                                $currentTimeOnly = Carbon::createFromFormat('H:i:s', $currentTimeInFormatted);

                                if ($earliestTimeOnly->lt($currentTimeOnly)) {
                                    $attendance->time_in = $earliestTimeInFormatted;
                                }
                            }
                        }

                        // Update time_out if biometric has later time
                        if ($latestTimeOut) {
                            $latestTimeOutFormatted = $latestTimeOut->format('H:i:s');
                            $currentTimeOutFormatted = $attendance->time_out;

                            // Only update if attendance has no time_out or biometric latest is later
                            if (! $currentTimeOutFormatted) {
                                $attendance->time_out = $latestTimeOutFormatted;
                            } else {
                                // Compare time portions only
                                $latestTimeOnly = Carbon::createFromFormat('H:i:s', $latestTimeOutFormatted);
                                $currentTimeOnly = Carbon::createFromFormat('H:i:s', $currentTimeOutFormatted);

                                if ($latestTimeOnly->gt($currentTimeOnly)) {
                                    $attendance->time_out = $latestTimeOutFormatted;
                                }
                            }
                        }

                        // Recalculate with current attendance settings
                        $this->updateAttendanceSummary($attendance, $attendanceSetting);
                        $attendance->save();
                        $updatedCount++;
                    }
                }
            }

            // Cleanup orphaned attendance records (employee_id doesn't exist)
            $orphanedAttendance = Attendance::query()
                ->whereDoesntHave('employee')
                ->get();

            foreach ($orphanedAttendance as $attendance) {
                $attendance->delete();
                $deletedCount++;
            }
        });

        return response()->json([
            'message' => 'Sync completed successfully.',
            'created' => $createdCount,
            'updated' => $updatedCount,
            'deleted' => $deletedCount,
        ], \Illuminate\Http\Response::HTTP_OK);
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
}
