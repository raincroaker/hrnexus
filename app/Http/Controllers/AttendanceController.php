<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkToggleOvertimeRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\StoreEmployeeLeaveRequest;
use App\Http\Requests\SyncAttendanceRequest;
use App\Http\Requests\ToggleOvertimeRequest;
use App\Http\Requests\StoreEmployeeOvertimeRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Requests\UpdateEmployeeLeaveRequest;
use App\Http\Requests\UpdateEmployeeOvertimeRequest;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\BiometricLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAbsentRecord;
use App\Models\EmployeeLateRecord;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveRecord;
use App\Models\EmployeeOvertime;
use App\Models\EmployeeWarningMemo;
use App\Models\Holiday;
use App\Models\LeaveType;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    /**
     * Centralized "now" for attendance context so seeded data and UI use the same date.
     * For production, this can simply return Carbon::now(config('app.timezone')).
     */
    private function currentDate(): Carbon
    {
        return Carbon::now(config('app.timezone'));
    }

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

        // Get late and absent counts for current employee
        $lateAbsentCounts = $this->getLateAbsentCounts($currentEmployee->id);

        // Get leave count and details for current month
        $myLeaveCount = $this->getLeaveCountForMonth($currentEmployee->id);
        $myLeaveDetails = $this->getMyLeaveDetails($currentEmployee->id);

        // Get optional date range filters from query parameters (use app timezone for current month)
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');
        $now = $this->currentDate();
        if (! $startDate || ! $endDate) {
            $startDate = $startDate ?? (clone $now)->startOfMonth()->toDateString();
            $endDate = $endDate ?? (clone $now)->endOfMonth()->toDateString();
        }
        $myAttendancePage = request()->query('my_attendance_page', 1);
        $teamAttendancePage = request()->query('team_attendance_page', 1);
        $teamStartDate = request()->query('team_start_date');
        $teamEndDate = request()->query('team_end_date');
        $teamSearch = request()->query('team_search');
        $teamStatus = request()->query('team_status');
        $todayStr = $this->currentDate()->toDateString();
        if ($teamStartDate === null && $teamEndDate === null) {
            // Default to start of week through today so Everyone tab and "This Week's Team Status" have data
            $teamStartDate = (clone $now)->startOfWeek()->toDateString();
            $teamEndDate = $todayStr;
        }
        $biometricLogsPage = request()->query('biometric_logs_page', 1);

        $currentMonth = $now->month;
        $currentYear = $now->year;
        $myActiveWarningMemos = EmployeeWarningMemo::query()
            ->where('employee_id', $currentEmployee->id)
            ->whereNull('resolved_at')
            ->where(function ($q) use ($currentMonth, $currentYear) {
                $q->where(function ($q2) use ($currentMonth, $currentYear) {
                    $q2->where('reason_type', 'like', 'late_%')
                        ->where('related_month', $currentMonth)
                        ->where('related_year', $currentYear);
                })->orWhere(function ($q2) use ($currentYear) {
                    $q2->where('reason_type', 'like', 'absent_%')
                        ->where('related_year', $currentYear);
                });
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EmployeeWarningMemo $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'reason_type' => $m->reason_type,
            ])
            ->values()
            ->all();

        $pageProps = [
            'currentUser' => $this->transformEmployee($currentEmployee),
            'attendanceSettings' => $resolvedAttendanceSettings,
            'myAttendance' => $this->attendanceCollection($currentEmployee->id, $startDate, $endDate, (int) $myAttendancePage, null, null, 'my_attendance_page'),
            'weeklyHours' => $this->getWeeklyHoursForEmployee($currentEmployee->id),
            'dtrStartDate' => $startDate,
            'dtrEndDate' => $endDate,
            'myLateCount' => $lateAbsentCounts['late_count'],
            'myAbsentCount' => $lateAbsentCounts['absent_count'],
            'myLateDates' => $this->getMyLateDatesForCurrentMonth($currentEmployee->id),
            'myAbsentDates' => $this->getMyAbsentDatesForCurrentMonth($currentEmployee->id),
            'myLeaveCount' => $myLeaveCount,
            'myLeaveDetails' => $myLeaveDetails,
            'canManageAll' => $canManageAll,
            'myActiveWarningMemos' => $myActiveWarningMemos,
        ];

        // Only admins can view all attendance and biometric logs
        if ($canManageAll) {
            $pageProps['teamAttendance'] = $this->attendanceCollection(
                null,
                $teamStartDate,
                $teamEndDate,
                (int) $teamAttendancePage,
                $teamSearch,
                $teamStatus,
                'team_attendance_page'
            );
            $pageProps['teamTableStartDate'] = $teamStartDate;
            $pageProps['teamTableEndDate'] = $teamEndDate;
            $pageProps['teamSearch'] = $teamSearch;
            $pageProps['teamStatus'] = $teamStatus;
            $pageProps['employees'] = $this->employeesCollection();
            // Biometric logs filtered to today only
            $pageProps['biometricLogs'] = $this->biometricLogCollection((int) $biometricLogsPage);
            // Team attendance summary for admin dashboard
            $pageProps['teamAttendanceSummary'] = $this->getTeamAttendanceSummary($resolvedAttendanceSettings);
            // Monthly leaves for all employees (admin only)
            $pageProps['monthlyLeaves'] = $this->getMonthlyLeaves();
            // Leave types for admin (to use in add leave form)
            $pageProps['leaveTypes'] = $this->getLeaveTypes();
            // Flat list of employee leave records for current month (On Leave modal CRUD)
            $pageProps['employeesOnLeaveRecords'] = $this->getEmployeesOnLeaveRecords();
            $pageProps['overtimeRecords'] = $this->getOvertimeRecordsForCurrentMonth();
        }

        return Inertia::render('Attendance', $pageProps);
    }

    /**
     * @param  string|null  $search  Filter by employee name/code (when loading all employees).
     * @param  string|null  $statusFilter  Filter by status: Present, Late, Absent, Leave, Holiday (when loading all employees).
     * @param  string  $pageName  Query string key used for pagination (e.g. my_attendance_page, team_attendance_page).
     */
    private function attendanceCollection(
        ?int $employeeId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        int $page = 1,
        ?string $search = null,
        ?string $statusFilter = null,
        string $pageName = 'page'
    ) {
        $perPage = 15;
        $query = Attendance::query()
            ->with(['employee.department', 'employee.position'])
            ->orderByDesc('date');

        $query->when(
            $employeeId,
            function ($builder) use ($employeeId, $startDate, $endDate) {
                if ($startDate && $endDate) {
                    $builder->where('employee_id', $employeeId)
                        ->whereBetween('date', [$startDate, $endDate]);
                } else {
                    $builder->where('employee_id', $employeeId)
                        ->whereMonth('date', Carbon::now()->month)
                        ->whereYear('date', Carbon::now()->year);
                }
            },
            function ($builder) use ($startDate, $endDate, $search, $statusFilter) {
                if ($startDate && $endDate) {
                    $builder->whereBetween('date', [$startDate, $endDate]);
                } else {
                    $builder->whereDate('date', Carbon::today());
                }
                if ($search !== null && $search !== '') {
                    $term = '%'.addcslashes($search, '%_\\').'%';
                    $builder->whereHas('employee', function ($q) use ($term) {
                        $q->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('employee_code', 'like', $term);
                    });
                }
                if ($statusFilter !== null && $statusFilter !== '') {
                    $builder->where('status', $statusFilter);
                }
            }
        );

        $paginated = $query->paginate($perPage, ['*'], $pageName, $page);

        $approvedOvertimeKeys = $this->getApprovedOvertimeKeysForAttendances($paginated->items());

        return [
            'data' => $paginated->map(fn (Attendance $attendance) => $this->transformAttendance($attendance, $approvedOvertimeKeys)),
            'links' => $paginated->linkCollection(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ];
    }

    /**
     * Get set of "employee_id|date" for (employee_id, date) that exist in employee_overtime.
     *
     * @param  array<Attendance>  $attendances
     * @return array<string>
     */
    private function getApprovedOvertimeKeysForAttendances(array $attendances): array
    {
        if (empty($attendances)) {
            return [];
        }

        $pairs = [];
        foreach ($attendances as $att) {
            $date = $att->date instanceof Carbon ? $att->date->toDateString() : $att->date;
            $pairs[] = ['employee_id' => $att->employee_id, 'date' => $date];
        }

        $employeeIds = array_unique(array_column($pairs, 'employee_id'));
        $pairKeys = array_flip(array_map(fn ($p) => $p['employee_id'].'|'.$p['date'], $pairs));

        $approved = EmployeeOvertime::query()
            ->whereIn('employee_id', $employeeIds)
            ->get(['employee_id', 'date']);

        return $approved
            ->map(function ($row) {
                $date = $row->date instanceof Carbon ? $row->date->toDateString() : $row->date;

                return $row->employee_id.'|'.$date;
            })
            ->filter(fn (string $key) => isset($pairKeys[$key]))
            ->unique()
            ->values()
            ->all();
    }

    private function employeesCollection()
    {
        return Employee::query()
            ->with(['department', 'position'])
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $employee) => $this->transformEmployee($employee));
    }

    private function biometricLogCollection(int $page = 1)
    {
        $perPage = 15;
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();

        $paginated = BiometricLog::query()
            ->whereBetween('scan_time', [$startOfMonth->startOfDay(), $endOfMonth->endOfDay()])
            ->orderByDesc('scan_time')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginated->map(fn (BiometricLog $log) => $this->transformBiometricLog($log)),
            'links' => $paginated->linkCollection(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ];
    }

    /**
     * @param  array<string>|null  $approvedOvertimeKeys  Set of "employee_id|date" for approved overtime; if not in set, overtime not shown in DTR
     */
    private function transformAttendance(Attendance $attendance, ?array $approvedOvertimeKeys = null): array
    {
        $employee = $attendance->employee;
        $date = $attendance->date instanceof Carbon ? $attendance->date->toDateString() : $attendance->date;

        $showOvertimeInDtr = ($attendance->is_overtime ?? false)
            && ($approvedOvertimeKeys === null || in_array($attendance->employee_id.'|'.$date, $approvedOvertimeKeys, true));

        // Fallback: when period times are null (e.g. only time_in/time_out set), use time_in/time_out for DTR display
        $morningTimeIn = $attendance->morning_time_in ?? $attendance->time_in;
        $morningTimeOut = $attendance->morning_time_out ?? $attendance->time_out;
        $afternoonTimeIn = $attendance->afternoon_time_in;
        $afternoonTimeOut = $attendance->afternoon_time_out;

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
            'morning_time_in' => $morningTimeIn,
            'morning_time_out' => $morningTimeOut,
            'afternoon_time_in' => $afternoonTimeIn,
            'afternoon_time_out' => $afternoonTimeOut,
            'overtime_time_in' => $showOvertimeInDtr ? $attendance->overtime_time_in : null,
            'overtime_time_out' => $showOvertimeInDtr ? $attendance->overtime_time_out : null,
            'is_overtime' => $attendance->is_overtime ?? false,
            'overtime_hours' => $attendance->overtime_hours,
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
            // Default thresholds (fallbacks):
            // - Lates: warning at 3, memorandum at 4
            // - Absences: warning at 3, memorandum at 4
            'late_threshold_warning' => $setting->late_threshold_warning ?? 3,
            'late_threshold_memo' => $setting->late_threshold_memo ?? 4,
            'absent_threshold_warning' => $setting->absent_threshold_warning ?? 3,
            'absent_threshold_memo' => $setting->absent_threshold_memo ?? 4,
        ];
    }

    /**
     * Get late and absent counts for an employee
     *
     * @return array{late_count: int, absent_count: int}
     */ 
    private function getLateAbsentCounts(int $employeeId): array
    {
        $now = $this->currentDate();
        $currentMonth = $now->month;
        $currentYear = $now->year;

        // Get late count for current month
        $lateRecord = EmployeeLateRecord::query()
            ->where('employee_id', $employeeId)
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->first();

        $lateCount = $lateRecord ? $lateRecord->total_lates : 0;

        // Get absent count for current year
        $absentRecord = EmployeeAbsentRecord::query()
            ->where('employee_id', $employeeId)
            ->where('year', $currentYear)
            ->first();

        $absentCount = $absentRecord ? $absentRecord->total_absents : 0;

        return [
            'late_count' => $lateCount,
            'absent_count' => $absentCount,
        ];
    }

    /**
     * Get last 7 days (today - 6 to today) with hours worked per day for the weekly bar chart.
     *
     * @return array<int, array{date: string, day: string, hours: float}>
     */
    private function getWeeklyHoursForEmployee(int $employeeId): array
    {
        $tz = config('app.timezone', 'Asia/Manila');
        $today = $this->currentDate()->copy()->startOfDay();
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = (clone $today)->subDays($i);
            $days[] = [
                'date' => $date->toDateString(),
                'day' => $date->format('D'),
                'hours' => 0.0,
            ];
        }
        $dateStrings = array_column($days, 'date');
        $records = Attendance::query()
            ->where('employee_id', $employeeId)
            ->whereIn('date', $dateStrings)
            ->get(['date', 'total_hours'])
            ->keyBy(fn (Attendance $a) => $a->date instanceof Carbon ? $a->date->toDateString() : $a->date);
        foreach ($days as $i => $day) {
            $record = $records[$day['date']] ?? null;
            $days[$i]['hours'] = $record && $record->total_hours !== null
                ? round((float) $record->total_hours, 2)
                : 0.0;
        }

        return $days;
    }

    /**
     * Get dates (Y-m-d) when the employee was late in the current month.
     *
     * @return array<string>
     */
    private function getMyLateDatesForCurrentMonth(int $employeeId): array
    {
        $now = $this->currentDate();
        $start = (clone $now)->startOfMonth()->toDateString();
        $end = $now->toDateString();

        return Attendance::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$start, $end])
            ->where('status', 'Late')
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : $d)
            ->values()
            ->all();
    }

    /**
     * Get dates (Y-m-d) when the employee was absent in the current month.
     *
     * @return array<string>
     */
    private function getMyAbsentDatesForCurrentMonth(int $employeeId): array
    {
        $now = $this->currentDate();
        $start = (clone $now)->startOfMonth()->toDateString();
        $end = $now->toDateString();

        return Attendance::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$start, $end])
            ->where('status', 'Absent')
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : $d)
            ->values()
            ->all();
    }

    private function getLeaveCountForMonth(int $employeeId): int
    {
        $now = $this->currentDate();
        $currentMonth = $now->month;
        $currentYear = $now->year;

        // Count leaves for current month
        return EmployeeLeave::query()
            ->where('employee_id', $employeeId)
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->count();
    }

    /**
     * Get employee summary stats (lates this month, absents this year, leaves this year)
     */
    public function getEmployeeSummary(Request $request, Employee $employee): JsonResponse
    {
        $user = Auth::user();
        $currentEmployee = Employee::query()
            ->where('email', $user->email)
            ->first();

        if (! $currentEmployee) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Only admins can access this
        if ($currentEmployee->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $now = $this->currentDate();
        $currentMonth = $now->month;
        $currentYear = $now->year;

        // Get late count for current month
        $lateRecord = EmployeeLateRecord::query()
            ->where('employee_id', $employee->id)
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->first();
        $lateCount = $lateRecord ? $lateRecord->total_lates : 0;

        // Get absent count for current year
        $absentRecord = EmployeeAbsentRecord::query()
            ->where('employee_id', $employee->id)
            ->where('year', $currentYear)
            ->first();
        $absentCount = $absentRecord ? $absentRecord->total_absents : 0;

        // Get leave count for current year
        $leaveCount = EmployeeLeave::query()
            ->where('employee_id', $employee->id)
            ->whereYear('date', $currentYear)
            ->count();

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->full_name,
                'department' => $employee->department?->name ?? 'N/A',
                'position' => $employee->position?->name ?? 'N/A',
                'email' => $employee->email,
                'contact_number' => $employee->contact_number,
            ],
            'late_count' => $lateCount,
            'absent_count' => $absentCount,
            'leave_count' => $leaveCount,
        ]);
    }

    private function getMyLeaveDetails(int $employeeId): array
    {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // Get all leave details for current employee this month
        $leaves = EmployeeLeave::query()
            ->with('leaveType')
            ->where('employee_id', $employeeId)
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->orderBy('date')
            ->get();

        return $leaves->map(function ($leave) {
            return [
                'id' => $leave->id,
                'date' => $leave->date instanceof Carbon ? $leave->date->toDateString() : $leave->date,
                'leave_type' => $leave->leaveType?->name ?? 'N/A',
                'notes' => $leave->notes,
            ];
        })->toArray();
    }

    private function getMonthlyLeaves(): array
    {
        $now = $this->currentDate();
        $currentMonth = $now->month;
        $currentYear = $now->year;

        // Get all employees on leave for current month
        $leaves = EmployeeLeave::query()
            ->with(['employee.department', 'employee.position', 'leaveType'])
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->orderBy('date')
            ->get();

        // Group by employee and collect dates
        $employeeLeaves = [];
        foreach ($leaves as $leave) {
            $employee = $leave->employee;
            $employeeId = $leave->employee_id;

            if (! isset($employeeLeaves[$employeeId])) {
                $employeeLeaves[$employeeId] = [
                    'employee_id' => $employeeId,
                    'employee_code' => $employee?->employee_code,
                    'name' => $employee?->full_name,
                    'department' => $employee?->department?->name ?? 'N/A',
                    'position' => $employee?->position?->name ?? 'N/A',
                    'leave_count' => 0,
                    'leave_dates' => [],
                    'leave_types' => [],
                ];
            }

            $employeeLeaves[$employeeId]['leave_count']++;
            $employeeLeaves[$employeeId]['leave_dates'][] = $leave->date instanceof Carbon
                ? $leave->date->toDateString()
                : $leave->date;
            $employeeLeaves[$employeeId]['leave_types'][] = $leave->leaveType?->name ?? 'N/A';
        }

        // Convert to array and sort by leave count DESC
        $result = array_values($employeeLeaves);
        usort($result, fn ($a, $b) => $b['leave_count'] <=> $a['leave_count']);

        return $result;
    }

    private function getLeaveTypes(): array
    {
        return LeaveType::query()
            ->orderBy('name')
            ->get()
            ->map(fn (LeaveType $leaveType) => [
                'id' => $leaveType->id,
                'name' => $leaveType->name,
                'annual_leaves' => $leaveType->annual_leaves,
            ])
            ->toArray();
    }

    /**
     * Flat list of employee leave records for the current month (for On Leave modal).
     *
     * @return array<int, array{id: int, employee_id: int, employee_name: string, leave_type_id: int, leave_type_name: string, date: string, notes: string|null}>
     */
    private function getEmployeesOnLeaveRecords(): array
    {
        $now = $this->currentDate();
        $currentMonth = $now->month;
        $currentYear = $now->year;

        return EmployeeLeave::query()
            ->with(['employee', 'leaveType'])
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get()
            ->map(function (EmployeeLeave $leave) {
                return [
                    'id' => $leave->id,
                    'employee_id' => $leave->employee_id,
                    'employee_name' => $leave->employee?->full_name ?? 'N/A',
                    'leave_type_id' => $leave->leave_type_id,
                    'leave_type_name' => $leave->leaveType?->name ?? 'N/A',
                    'date' => $leave->date instanceof Carbon ? $leave->date->toDateString() : $leave->date,
                    'notes' => $leave->notes,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Overtime records for the current month (for Overtime card/modal).
     *
     * @return array<int, array{id: int, employee_id: int, employee_name: string, date: string}>
     */
    private function getOvertimeRecordsForCurrentMonth(): array
    {
        $now = $this->currentDate();
        $currentMonth = $now->month;
        $currentYear = $now->year;

        return EmployeeOvertime::query()
            ->with('employee')
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get()
            ->map(function (EmployeeOvertime $ot) {
                return [
                    'id' => $ot->id,
                    'employee_id' => $ot->employee_id,
                    'employee_name' => $ot->employee?->full_name ?? 'N/A',
                    'date' => $ot->date instanceof Carbon ? $ot->date->toDateString() : $ot->date,
                ];
            })
            ->values()
            ->toArray();
    }

    public function storeEmployeeOvertime(StoreEmployeeOvertimeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = Auth::user();

        $existing = EmployeeOvertime::query()
            ->where('employee_id', $data['employee_id'])
            ->whereDate('date', $data['date'])
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'An overtime record already exists for this employee on this date.',
            ], 422);
        }

        $overtime = EmployeeOvertime::query()->create([
            'employee_id' => $data['employee_id'],
            'date' => $data['date'],
            'approved_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Overtime record added.',
            'overtime' => [
                'id' => $overtime->id,
                'employee_id' => $overtime->employee_id,
                'date' => $overtime->date instanceof Carbon ? $overtime->date->toDateString() : $overtime->date,
            ],
        ], 201);
    }

    public function updateEmployeeOvertime(UpdateEmployeeOvertimeRequest $request, EmployeeOvertime $employeeOvertime): JsonResponse
    {
        $data = $request->validated();

        $employeeOvertime->update(['date' => $data['date']]);

        return response()->json([
            'message' => 'Overtime record updated.',
            'overtime' => [
                'id' => $employeeOvertime->id,
                'employee_id' => $employeeOvertime->employee_id,
                'date' => $employeeOvertime->date instanceof Carbon ? $employeeOvertime->date->toDateString() : $employeeOvertime->date,
            ],
        ]);
    }

    public function destroyEmployeeOvertime(EmployeeOvertime $employeeOvertime): JsonResponse
    {
        $employeeOvertime->delete();

        return response()->json(['message' => 'Overtime record deleted.']);
    }

    public function storeEmployeeLeave(StoreEmployeeLeaveRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = Auth::user();

        DB::beginTransaction();

        try {
            // Create employee leave record
            $employeeLeave = EmployeeLeave::query()->create([
                'employee_id' => $data['employee_id'],
                'leave_type_id' => $data['leave_type_id'],
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
                'set_by' => $user->id,
            ]);

            // Update employee leave record (increment total_leaves for the year)
            $year = Carbon::parse($data['date'])->year;
            $this->incrementLeaveRecord($data['employee_id'], $data['leave_type_id'], $year);

            DB::commit();

            return response()->json([
                'message' => 'Employee leave added successfully.',
                'employee_leave' => [
                    'id' => $employeeLeave->id,
                    'employee_id' => $employeeLeave->employee_id,
                    'leave_type_id' => $employeeLeave->leave_type_id,
                    'date' => $employeeLeave->date instanceof Carbon
                        ? $employeeLeave->date->toDateString()
                        : $employeeLeave->date,
                    'notes' => $employeeLeave->notes,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to add employee leave.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateEmployeeLeave(UpdateEmployeeLeaveRequest $request, EmployeeLeave $employeeLeave): JsonResponse
    {
        $data = $request->validated();

        DB::beginTransaction();

        try {
            $oldDate = $employeeLeave->date instanceof Carbon ? $employeeLeave->date : Carbon::parse($employeeLeave->date);
            $oldYear = $oldDate->year;
            $oldLeaveTypeId = $employeeLeave->leave_type_id;
            $employeeId = $employeeLeave->employee_id;

            $employeeLeave->update([
                'leave_type_id' => $data['leave_type_id'],
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
            ]);

            $newDate = Carbon::parse($data['date']);
            $newYear = $newDate->year;
            $newLeaveTypeId = (int) $data['leave_type_id'];

            $this->decrementLeaveRecord($employeeId, $oldLeaveTypeId, $oldYear);
            $this->incrementLeaveRecord($employeeId, $newLeaveTypeId, $newYear);

            DB::commit();

            return response()->json([
                'message' => 'Employee leave updated successfully.',
                'employee_leave' => [
                    'id' => $employeeLeave->id,
                    'employee_id' => $employeeLeave->employee_id,
                    'leave_type_id' => $employeeLeave->leave_type_id,
                    'date' => $employeeLeave->date instanceof Carbon ? $employeeLeave->date->toDateString() : $employeeLeave->date,
                    'notes' => $employeeLeave->notes,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update employee leave.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroyEmployeeLeave(EmployeeLeave $employeeLeave): JsonResponse
    {
        DB::beginTransaction();

        try {
            $date = $employeeLeave->date instanceof Carbon ? $employeeLeave->date : Carbon::parse($employeeLeave->date);
            $year = $date->year;
            $this->decrementLeaveRecord($employeeLeave->employee_id, $employeeLeave->leave_type_id, $year);
            $employeeLeave->delete();
            DB::commit();

            return response()->json(['message' => 'Employee leave deleted successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete employee leave.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function incrementLeaveRecord(int $employeeId, int $leaveTypeId, int $year): void
    {
        $record = EmployeeLeaveRecord::query()->firstOrCreate(
            [
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
            ],
            [
                'total_leaves' => 0,
            ]
        );

        $record->increment('total_leaves');
    }

    private function decrementLeaveRecord(int $employeeId, int $leaveTypeId, int $year): void
    {
        $record = EmployeeLeaveRecord::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        if ($record && $record->total_leaves > 0) {
            $record->decrement('total_leaves');
        }
    }

    /**
     * Export all attendance records (aggregated per employee) for a given month/year as CSV.
     */
    public function exportCsv(): StreamedResponse|JsonResponse
    {
        $validated = request()->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $user = Auth::user();
        $currentEmployee = Employee::query()
            ->where('email', $user->email)
            ->first();

        if (! $currentEmployee || $currentEmployee->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $departmentId = $validated['department_id'] ?? null;
        $departmentLabel = 'All Departments';

        if ($departmentId) {
            $dept = Department::find($departmentId);
            if (! $dept) {
                return response()->json(['message' => 'Department not found.'], 404);
            }
            $departmentLabel = $dept->name;
        }

        // Fetch employees (filtered by department if provided)
        $employees = Employee::query()
            ->with(['department', 'position'])
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        // Attendance aggregates (hours, lates, absents, first/last date)
        $attendanceAgg = Attendance::query()
            ->selectRaw('employee_id,
                SUM(COALESCE(total_hours, 0)) as total_hours,
                SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as lates,
                SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absents,
                MIN(date) as first_date,
                MAX(date) as last_date')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->when(
                $departmentId,
                fn ($q) => $q->whereHas('employee', fn ($qq) => $qq->where('department_id', $departmentId))
            )
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        // Leave aggregates
        $leaveAgg = EmployeeLeave::query()
            ->selectRaw('employee_id, COUNT(*) as leaves')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->when(
                $departmentId,
                fn ($q) => $q->whereHas('employee', fn ($qq) => $qq->where('department_id', $departmentId))
            )
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        // Leave breakdown by type (e.g., SL:2; VL:1)
        $leaveBreakdowns = EmployeeLeave::query()
            ->selectRaw('employee_id, leave_type_id, COUNT(*) as total')
            ->with('leaveType')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->when(
                $departmentId,
                fn ($q) => $q->whereHas('employee', fn ($qq) => $qq->where('department_id', $departmentId))
            )
            ->groupBy('employee_id', 'leave_type_id')
            ->get()
            ->groupBy('employee_id');

        $attendanceSetting = AttendanceSetting::query()->latest()->first()
            ?? new AttendanceSetting(AttendanceSetting::defaultValues());
        $lateWarningThreshold = $attendanceSetting->late_threshold_warning ?? 3;
        $lateMemoThreshold = $attendanceSetting->late_threshold_memo ?? 4;
        $absentWarningThreshold = $attendanceSetting->absent_threshold_warning ?? 3;
        $absentMemoThreshold = $attendanceSetting->absent_threshold_memo ?? 4;

        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $monthEnd = (clone $monthStart)->endOfMonth();
        $today = Carbon::today();
        $periodEnd = $monthEnd->lessThan($today) ? $monthEnd : $today;
        // Safety: if the requested month is entirely in the future, keep periodEnd at monthEnd to avoid empty ranges
        if ($periodEnd->lessThan($monthStart)) {
            $periodEnd = $monthEnd;
        }

        $holidays = Holiday::query()
            ->whereBetween('date', [$monthStart->toDateString(), $periodEnd->toDateString()])
            ->orderBy('date')
            ->get();

        $holidayDates = $holidays->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        $holidaySet = $holidayDates->flip();
        $holidayCount = $holidayDates->count();

        $workingDays = 0;
        foreach (CarbonPeriod::create($monthStart, $monthEnd) as $day) {
            if ($day->isSunday()) {
                continue;
            }

            if ($holidaySet->has($day->toDateString())) {
                continue;
            }

            $workingDays++;
        }

        $fileName = sprintf('attendance-%04d-%02d.csv', $year, $month);

        return response()->streamDownload(function () use (
            $employees,
            $attendanceAgg,
            $leaveAgg,
            $leaveBreakdowns,
            $year,
            $month,
            $departmentLabel,
            $workingDays,
            $holidayCount,
            $lateWarningThreshold,
            $lateMemoThreshold,
            $absentWarningThreshold,
            $absentMemoThreshold,
            $monthStart,
            $periodEnd,
            $holidays
        ) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Metadata header for payroll-friendly context
            fputcsv($handle, ['Report', 'All Attendance (Monthly Aggregate)']);
            fputcsv($handle, ['Period', sprintf('%04d-%02d', $year, $month)]);
            fputcsv($handle, ['Date Range', sprintf('%s to %s', $monthStart->format('Y-m-d'), $periodEnd->format('Y-m-d'))]);
            fputcsv($handle, ['Department', $departmentLabel]);
            fputcsv($handle, ['Generated at', Carbon::now()->toDateTimeString()]);
            fputcsv($handle, []); // Blank line
            fputcsv($handle, ['Working Days', 'Holidays']);
            fputcsv($handle, [sprintf('%d (excluding Sundays & holidays)', $workingDays), $holidayCount]);
            fputcsv($handle, []); // Blank line

            // List holidays if any
            if ($holidays->count() > 0) {
                fputcsv($handle, ['Holidays in Period']);
                fputcsv($handle, ['Date', 'Holiday Name']);
                foreach ($holidays as $holiday) {
                    $holidayDate = match (true) {
                        is_string($holiday->date) => Carbon::parse($holiday->date)->format('Y-m-d'),
                        $holiday->date instanceof Carbon => $holiday->date->format('Y-m-d'),
                        default => Carbon::parse($holiday->date)->format('Y-m-d'),
                    };
                    fputcsv($handle, [$holidayDate, $holiday->name]);
                }
                fputcsv($handle, []); // Blank line
            }

            fputcsv($handle, [
                'Employee Code',
                'Name',
                'Department',
                'Position',
                'Working Days',
                'Holidays',
                'Total Hours Worked',
                'Lates',
                'Absents',
                'Leaves',
                'Leave Breakdown',
                'Remarks',
            ]);

            foreach ($employees as $employee) {
                $att = $attendanceAgg[$employee->id] ?? null;
                $leaves = $leaveAgg[$employee->id]->leaves ?? 0;
                $leaveTypes = $leaveBreakdowns[$employee->id] ?? collect();
                $leaveBreakdownStr = $leaveTypes->map(function ($row) {
                    $type = $row->leaveType?->name ?? 'Leave';

                    return $type.':'.$row->total;
                })->implode('; ');

                $totalHours = $att ? round((float) $att->total_hours, 2) : 0;
                $lates = $att ? (int) $att->lates : 0;
                $absents = $att ? (int) $att->absents : 0;

                $remarks = '';
                if (! $att) {
                    $remarks = 'No attendance records for period';
                } else {
                    $remarkParts = [];

                    if ($lates >= $lateMemoThreshold) {
                        $remarkParts[] = 'Late memo threshold reached ('.$lates.'/'.$lateMemoThreshold.')';
                    } elseif ($lates >= $lateWarningThreshold) {
                        $remarkParts[] = 'Late warning threshold reached ('.$lates.'/'.$lateWarningThreshold.')';
                    }

                    if ($absents >= $absentMemoThreshold) {
                        $remarkParts[] = 'Absent memo threshold reached ('.$absents.'/'.$absentMemoThreshold.')';
                    } elseif ($absents >= $absentWarningThreshold) {
                        $remarkParts[] = 'Absent warning threshold reached ('.$absents.'/'.$absentWarningThreshold.')';
                    }

                    $remarks = implode('; ', $remarkParts);
                }

                fputcsv($handle, [
                    $employee->employee_code,
                    $employee->full_name,
                    $employee->department?->name ?? '',
                    $employee->position?->name ?? '',
                    $workingDays,
                    $holidayCount,
                    $totalHours,
                    $lates,
                    $absents,
                    $leaves,
                    $leaveBreakdownStr,
                    $remarks,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Export a single employee's attendance for a given month/year as PDF.
     */
    public function exportEmployeePdf(Request $request, Employee $employee): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'current_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $user = Auth::user();
        $currentEmployee = Employee::query()
            ->where('email', $user->email)
            ->first();

        if (! $currentEmployee) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $isPrivileged = in_array($currentEmployee->role, ['admin', 'department_manager'], true);
        $isSelf = $currentEmployee->id === $employee->id;

        if (! $isPrivileged && ! $isSelf) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        $attendanceSetting = AttendanceSetting::query()->latest()->first()
            ?? new AttendanceSetting(AttendanceSetting::defaultValues());

        $lateWarningThreshold = $attendanceSetting->late_threshold_warning ?? 3;
        $lateMemoThreshold = $attendanceSetting->late_threshold_memo ?? 4;
        $absentWarningThreshold = $attendanceSetting->absent_threshold_warning ?? 3;
        $absentMemoThreshold = $attendanceSetting->absent_threshold_memo ?? 4;

        $timezone = config('app.timezone', 'Asia/Manila');
        $monthStart = Carbon::createFromDate($year, $month, 1, $timezone)->startOfDay();
        $monthEnd = (clone $monthStart)->endOfMonth();
        $today = isset($validated['current_date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['current_date'], $timezone)->startOfDay()
            : Carbon::now($timezone);

        // Determine period end:
        // - Future month: no range
        // - Current month: cap at today
        // - Past month: full month
        if ($monthStart->greaterThan($today)) {
            $hasRange = false;
            $periodEnd = $monthEnd;
        } elseif ($monthStart->isSameMonth($today) && $monthStart->isSameYear($today)) {
            $periodEnd = $today;
            $hasRange = true;
        } else {
            $periodEnd = $monthEnd;
            $hasRange = true;
        }

        $holidayDates = $hasRange
            ? Holiday::query()
                ->whereBetween('date', [$monthStart->toDateString(), $periodEnd->toDateString()])
                ->pluck('date')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->unique()
                ->values()
            : collect();
        $holidaySet = $holidayDates->flip();
        $holidayCount = $holidayDates->count();

        $attendanceRecords = $hasRange
            ? Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('date', [$monthStart->toDateString(), $periodEnd->toDateString()])
                ->get()
                ->keyBy(fn ($att) => Carbon::parse($att->date)->toDateString())
            : collect();

        $leaveDatesSet = $hasRange
            ? EmployeeLeave::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('date', [$monthStart->toDateString(), $periodEnd->toDateString()])
                ->pluck('date')
                ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : $d)
                ->flip()
            : collect();

        $approvedOvertimeDatesSet = $hasRange
            ? EmployeeOvertime::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('date', [$monthStart->toDateString(), $periodEnd->toDateString()])
                ->pluck('date')
                ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : $d)
                ->flip()
            : collect();

        $workingDays = 0;
        $rows = [];
        $totalHours = 0;
        $lateCount = 0;
        $absentCount = 0;
        $leaveCount = 0;

        $formatTime = function (?string $time, string $date): ?string {
            if (! $time) {
                return null;
            }

            try {
                return Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$time)->format('h:i A');
            } catch (\Exception $e) {
                try {
                    return Carbon::parse($date.' '.$time)->format('h:i A');
                } catch (\Exception $e) {
                    return $time;
                }
            }
        };

        if ($hasRange) {
            foreach (CarbonPeriod::create($monthStart, $periodEnd) as $day) {
                if ($day->isSunday()) {
                    continue;
                }

                $dateKey = $day->toDateString();
                $isHoliday = $holidaySet->has($dateKey);
                if (! $isHoliday) {
                    $workingDays++;
                }

                $record = $attendanceRecords[$dateKey] ?? null;

                $status = 'Absent';
                $remarks = 'Absent';
                $morningTimeIn = null;
                $morningTimeOut = null;
                $afternoonTimeIn = null;
                $afternoonTimeOut = null;
                $overtimeTimeIn = null;
                $overtimeTimeOut = null;
                $hoursWorked = 0.0;

                if ($isHoliday) {
                    $status = 'Holiday';
                    $remarks = 'Holiday';
                } elseif ($leaveDatesSet->has($dateKey)) {
                    // Hard rule for DTR export: if there's a leave record for this day,
                    // treat it as Leave even if an attendance record exists.
                    $status = 'Leave';
                    $remarks = 'On leave';
                } elseif ($record) {
                    $status = ucfirst(strtolower($record->status));
                    $remarks = $record->remarks ?? $status;
                    $morningTimeIn = $formatTime($record->morning_time_in, $dateKey);
                    $morningTimeOut = $formatTime($record->morning_time_out, $dateKey);
                    $afternoonTimeIn = $formatTime($record->afternoon_time_in, $dateKey);
                    $afternoonTimeOut = $formatTime($record->afternoon_time_out, $dateKey);
                    $showOvertimeInDtr = ($record->is_overtime ?? false) && $approvedOvertimeDatesSet->has($dateKey);
                    $overtimeTimeIn = $showOvertimeInDtr ? $formatTime($record->overtime_time_in, $dateKey) : null;
                    $overtimeTimeOut = $showOvertimeInDtr ? $formatTime($record->overtime_time_out, $dateKey) : null;
                    if ($morningTimeIn === null && $afternoonTimeIn === null && $record->time_in) {
                        $morningTimeIn = $formatTime($record->time_in, $dateKey);
                    }
                    if ($morningTimeOut === null && $afternoonTimeOut === null && $record->time_out) {
                        $afternoonTimeOut = $formatTime($record->time_out, $dateKey);
                    }
                    $hoursWorked = $record->total_hours ? round((float) $record->total_hours, 2) : 0.0;
                }

                if ($status === 'Late') {
                    $lateCount++;
                }
                if ($status === 'Absent') {
                    $absentCount++;
                }
                if ($status === 'Leave') {
                    $leaveCount++;
                }
                if ($status !== 'Holiday') {
                    $totalHours += $hoursWorked;
                }

                $rows[] = [
                    'date' => $day->format('Y-m-d'),
                    'day' => $day->format('D'),
                    'morning_time_in' => $morningTimeIn,
                    'morning_time_out' => $morningTimeOut,
                    'afternoon_time_in' => $afternoonTimeIn,
                    'afternoon_time_out' => $afternoonTimeOut,
                    'overtime_time_in' => $overtimeTimeIn,
                    'overtime_time_out' => $overtimeTimeOut,
                    'status' => $status,
                    'hours' => $hoursWorked,
                    'is_holiday' => $isHoliday,
                ];
            }
        }

        $lateWarningHit = $lateCount >= $lateWarningThreshold;
        $lateMemoHit = $lateCount >= $lateMemoThreshold;
        $absentWarningHit = $absentCount >= $absentWarningThreshold;
        $absentMemoHit = $absentCount >= $absentMemoThreshold;

        $avatarData = null;
        if ($employee->avatar) {
            $avatarPath = null;
            if (Storage::exists($employee->avatar)) {
                $avatarPath = Storage::path($employee->avatar);
            } elseif (Storage::disk('public')->exists($employee->avatar)) {
                $avatarPath = Storage::disk('public')->path($employee->avatar);
            }

            if ($avatarPath && file_exists($avatarPath)) {
                $mimeType = mime_content_type($avatarPath) ?: 'image/png';
                $avatarData = 'data:'.$mimeType.';base64,'.base64_encode(file_get_contents($avatarPath));
            }
        }

        $pdf = Pdf::loadView('attendance.pdf', [
            'employee' => $employee,
            'department' => $employee->department,
            'position' => $employee->position,
            'email' => $employee->email,
            'contact_number' => $employee->contact_number,
            'avatar' => $avatarData,
            'period_label' => $monthStart->format('F Y'),
            'date_range' => $hasRange
                ? $monthStart->format('M d, Y').' - '.$periodEnd->format('M d, Y')
                : $monthStart->format('M d, Y').' - '.$monthEnd->format('M d, Y'),
            'working_days' => $workingDays,
            'holiday_count' => $holidayCount,
            'total_hours' => round($totalHours, 2),
            'late_count' => $lateCount,
            'absent_count' => $absentCount,
            'leave_count' => $leaveCount,
            'late_warning_hit' => $lateWarningHit,
            'late_memo_hit' => $lateMemoHit,
            'absent_warning_hit' => $absentWarningHit,
            'absent_memo_hit' => $absentMemoHit,
            'rows' => $rows,
            'generated_at' => Carbon::now()->toDateTimeString(),
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf('attendance-%s-%04d-%02d.pdf', $employee->employee_code ?? $employee->id, $year, $month);

        return $pdf->stream($fileName);
    }

    /**
     * Get team attendance summary for admin dashboard
     *
     * @param  array<string, mixed>  $attendanceSettings
     * @return array<string, mixed>
     */
    /**
     * Check if an unresolved warning/memo exists for an employee.
     */
    private function hasUnresolvedWarningMemo(int $employeeId, string $reasonType, ?int $month, int $year): bool
    {
        $query = EmployeeWarningMemo::query()
            ->where('employee_id', $employeeId)
            ->where('reason_type', $reasonType)
            ->where('related_year', $year)
            ->whereNull('resolved_at'); // Only check unresolved

        if ($month !== null) {
            $query->where('related_month', $month);
        } else {
            $query->whereNull('related_month');
        }

        return $query->exists();
    }

    /**
     * Get unresolved warning/memo record IDs for an employee (current month/year).
     */
    private function getUnresolvedWarningMemoIds(int $employeeId, int $currentMonth, int $currentYear): array
    {
        $ids = EmployeeWarningMemo::query()
            ->where('employee_id', $employeeId)
            ->whereNull('resolved_at')
            ->where(function ($q) use ($currentMonth, $currentYear) {
                $q->where(function ($q2) use ($currentMonth, $currentYear) {
                    $q2->where('reason_type', 'like', 'late_%')
                        ->where('related_month', $currentMonth)
                        ->where('related_year', $currentYear);
                })->orWhere(function ($q2) use ($currentYear) {
                    $q2->where('reason_type', 'like', 'absent_%')
                        ->where('related_year', $currentYear);
                });
            })
            ->orderByDesc('created_at')
            ->pluck('id')
            ->values()
            ->all();

        return $ids;
    }

    private function getTeamAttendanceSummary(array $attendanceSettings): array
    {
        $now = $this->currentDate();
        $currentMonth = $now->month;
        $currentYear = $now->year;
        $today = $now->copy()->startOfDay();
        $yesterday = $today->copy()->subDay();

        $lateThresholdWarning = $attendanceSettings['late_threshold_warning'] ?? 3;
        $lateThresholdMemo = $attendanceSettings['late_threshold_memo'] ?? 4;
        $absentThresholdWarning = $attendanceSettings['absent_threshold_warning'] ?? 3;
        $absentThresholdMemo = $attendanceSettings['absent_threshold_memo'] ?? 4;

        // 1. Today's lates - employees with status='Late' today
        $todayLates = Attendance::query()
            ->with(['employee.department', 'employee.position'])
            ->whereDate('date', $today)
            ->where('status', 'Late')
            ->get()
            ->map(function (Attendance $attendance) use ($currentMonth, $currentYear) {
                $employee = $attendance->employee;

                return [
                    'employee_id' => $attendance->employee_id,
                    'employee_code' => $employee?->employee_code,
                    'name' => $employee?->full_name,
                    'department' => $employee?->department?->name ?? 'N/A',
                    'position' => $employee?->position?->name ?? 'N/A',
                    'time_in' => $attendance->time_in,
                    'date' => $attendance->date,
                    'has_late_warning' => $this->hasUnresolvedWarningMemo($attendance->employee_id, 'late_warning', $currentMonth, $currentYear),
                    'has_late_memo' => $this->hasUnresolvedWarningMemo($attendance->employee_id, 'late_memo', $currentMonth, $currentYear),
                ];
            })
            ->values()
            ->toArray();

        // 1.5. Yesterday's absents - employees with status='Absent' yesterday
        $yesterdayAbsents = Attendance::query()
            ->with(['employee.department', 'employee.position'])
            ->whereDate('date', $yesterday)
            ->where('status', 'Absent')
            ->get()
            ->map(function (Attendance $attendance) use ($currentYear) {
                $employee = $attendance->employee;

                return [
                    'employee_id' => $attendance->employee_id,
                    'employee_code' => $employee?->employee_code,
                    'name' => $employee?->full_name,
                    'department' => $employee?->department?->name ?? 'N/A',
                    'position' => $employee?->position?->name ?? 'N/A',
                    'date' => $attendance->date,
                    'has_absent_warning' => $this->hasUnresolvedWarningMemo($attendance->employee_id, 'absent_warning', null, $currentYear),
                    'has_absent_memo' => $this->hasUnresolvedWarningMemo($attendance->employee_id, 'absent_memo', null, $currentYear),
                ];
            })
            ->values()
            ->toArray();

        // 1.6. Today's leaves - employees with status='Leave' today
        $todayLeaves = Attendance::query()
            ->with(['employee.department', 'employee.position'])
            ->whereDate('date', $today)
            ->where('status', 'Leave')
            ->get()
            ->map(function (Attendance $attendance) {
                $employee = $attendance->employee;

                return [
                    'employee_id' => $attendance->employee_id,
                    'employee_code' => $employee?->employee_code,
                    'name' => $employee?->full_name,
                    'department' => $employee?->department?->name ?? 'N/A',
                    'position' => $employee?->position?->name ?? 'N/A',
                    'date' => $attendance->date,
                ];
            })
            ->values()
            ->toArray();

        // 2. Monthly lates - employees with lates this month, sorted by count DESC
        $monthlyLates = EmployeeLateRecord::query()
            ->with(['employee.department', 'employee.position'])
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->where('total_lates', '>', 0)
            ->orderByDesc('total_lates')
            ->get()
            ->map(function (EmployeeLateRecord $record) use ($currentMonth, $currentYear) {
                $employee = $record->employee;

                return [
                    'employee_id' => $record->employee_id,
                    'employee_code' => $employee?->employee_code,
                    'name' => $employee?->full_name,
                    'department' => $employee?->department?->name ?? 'N/A',
                    'position' => $employee?->position?->name ?? 'N/A',
                    'late_count' => $record->total_lates,
                    'month' => $record->month,
                    'year' => $record->year,
                    'has_late_warning' => $this->hasUnresolvedWarningMemo($record->employee_id, 'late_warning', $currentMonth, $currentYear),
                    'has_late_memo' => $this->hasUnresolvedWarningMemo($record->employee_id, 'late_memo', $currentMonth, $currentYear),
                ];
            })
            ->values()
            ->toArray();

        // 3. Yearly absents - employees with absents this year, sorted by count DESC
        $yearlyAbsents = EmployeeAbsentRecord::query()
            ->with(['employee.department', 'employee.position'])
            ->where('year', $currentYear)
            ->where('total_absents', '>', 0)
            ->orderByDesc('total_absents')
            ->get()
            ->map(function (EmployeeAbsentRecord $record) use ($currentYear) {
                $employee = $record->employee;

                return [
                    'employee_id' => $record->employee_id,
                    'employee_code' => $employee?->employee_code,
                    'name' => $employee?->full_name,
                    'department' => $employee?->department?->name ?? 'N/A',
                    'position' => $employee?->position?->name ?? 'N/A',
                    'absent_count' => $record->total_absents,
                    'year' => $record->year,
                    'has_absent_warning' => $this->hasUnresolvedWarningMemo($record->employee_id, 'absent_warning', null, $currentYear),
                    'has_absent_memo' => $this->hasUnresolvedWarningMemo($record->employee_id, 'absent_memo', null, $currentYear),
                ];
            })
            ->values()
            ->toArray();

        // 3.5. Late / absent counts and last dates (for Warning tab details)
        $lateCountsByEmployee = Attendance::query()
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->where('status', 'Late')
            ->selectRaw('employee_id, COUNT(*) as total_lates')
            ->groupBy('employee_id')
            ->pluck('total_lates', 'employee_id');

        $absentCountsByEmployee = Attendance::query()
            ->whereYear('date', $currentYear)
            ->where('status', 'Absent')
            ->selectRaw('employee_id, COUNT(*) as total_absents')
            ->groupBy('employee_id')
            ->pluck('total_absents', 'employee_id');

        $lastLateDates = Attendance::query()
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->where('status', 'Late')
            ->selectRaw('employee_id, MAX(date) as last_late_date')
            ->groupBy('employee_id')
            ->pluck('last_late_date', 'employee_id');

        $lastAbsentDates = Attendance::query()
            ->whereYear('date', $currentYear)
            ->where('status', 'Absent')
            ->selectRaw('employee_id, MAX(date) as last_absent_date')
            ->groupBy('employee_id')
            ->pluck('last_absent_date', 'employee_id');

        // 4. Warning employees - employees at warning threshold
        $warningEmployees = [];
        $allEmployees = Employee::query()
            ->with(['department', 'position'])
            ->get();

        foreach ($allEmployees as $employee) {
            $lateCount = (int) ($lateCountsByEmployee[$employee->id] ?? 0);
            $absentCount = (int) ($absentCountsByEmployee[$employee->id] ?? 0);

            $isLateWarning = $lateCount >= $lateThresholdWarning;
            $isAbsentWarning = $absentCount >= $absentThresholdWarning;

            if ($isLateWarning || $isAbsentWarning) {
                $status = [];
                if ($isLateWarning) {
                    $status[] = 'late_warning';
                }
                if ($isAbsentWarning) {
                    $status[] = 'absent_warning';
                }

                $warningEmployees[] = [
                    'employee_id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'name' => $employee->full_name,
                    'department' => $employee->department?->name ?? 'N/A',
                    'position' => $employee->position?->name ?? 'N/A',
                    'late_count' => $lateCount,
                    'absent_count' => $absentCount,
                    'status' => $status,
                    'has_late_warning' => $isLateWarning ? $this->hasUnresolvedWarningMemo($employee->id, 'late_warning', $currentMonth, $currentYear) : false,
                    'has_absent_warning' => $isAbsentWarning ? $this->hasUnresolvedWarningMemo($employee->id, 'absent_warning', null, $currentYear) : false,
                    'warning_memo_ids' => $this->getUnresolvedWarningMemoIds($employee->id, $currentMonth, $currentYear),
                    'last_late_date' => $lastLateDates[$employee->id] ?? null,
                    'last_absent_date' => $lastAbsentDates[$employee->id] ?? null,
                ];
            }
        }

        // Sort warning employees by total issues (late + absent) DESC
        usort($warningEmployees, function ($a, $b) {
            $totalA = $a['late_count'] + $a['absent_count'];
            $totalB = $b['late_count'] + $b['absent_count'];

            return $totalB <=> $totalA;
        });

        // 5. Memo employees - employees at memo threshold
        $memoEmployees = [];
        foreach ($allEmployees as $employee) {
            $lateCount = (int) ($lateCountsByEmployee[$employee->id] ?? 0);
            $absentCount = (int) ($absentCountsByEmployee[$employee->id] ?? 0);

            $isLateMemo = $lateCount >= $lateThresholdMemo;
            $isAbsentMemo = $absentCount >= $absentThresholdMemo;

            if ($isLateMemo || $isAbsentMemo) {
                $status = [];
                if ($isLateMemo) {
                    $status[] = 'late_memo';
                }
                if ($isAbsentMemo) {
                    $status[] = 'absent_memo';
                }

                $memoEmployees[] = [
                    'employee_id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'name' => $employee->full_name,
                    'department' => $employee->department?->name ?? 'N/A',
                    'position' => $employee->position?->name ?? 'N/A',
                    'late_count' => $lateCount,
                    'absent_count' => $absentCount,
                    'status' => $status,
                    'has_late_memo' => $isLateMemo ? $this->hasUnresolvedWarningMemo($employee->id, 'late_memo', $currentMonth, $currentYear) : false,
                    'has_absent_memo' => $isAbsentMemo ? $this->hasUnresolvedWarningMemo($employee->id, 'absent_memo', null, $currentYear) : false,
                    'warning_memo_ids' => $this->getUnresolvedWarningMemoIds($employee->id, $currentMonth, $currentYear),
                    'last_late_date' => $lastLateDates[$employee->id] ?? null,
                    'last_absent_date' => $lastAbsentDates[$employee->id] ?? null,
                ];
            }
        }

        // Sort memo employees by total issues (late + absent) DESC
        usort($memoEmployees, function ($a, $b) {
            $totalA = $a['late_count'] + $a['absent_count'];
            $totalB = $b['late_count'] + $b['absent_count'];

            return $totalB <=> $totalA;
        });

        // Today's status counts for pie chart (Present, Late, On Leave only)
        $todayCounts = Attendance::query()
            ->whereDate('date', $today)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->get()
            ->keyBy('status');
        $todayStatusCounts = [
            'present' => (int) (($todayCounts['Present'] ?? null)?->cnt ?? 0),
            'late' => (int) (($todayCounts['Late'] ?? null)?->cnt ?? 0),
            'on_leave' => (int) (($todayCounts['Leave'] ?? null)?->cnt ?? 0),
        ];
        $totalEmployees = Employee::query()->count();

        // Weekly status counts for "This Week's Team Status" bar chart (always this week, independent of table filter)
        $startOfWeek = (clone $now)->startOfWeek();
        $weeklyStatusCounts = [];
        $d = $startOfWeek->copy();
        while ($d->lte($today)) {
            if ($d->dayOfWeek !== Carbon::SUNDAY) {
                $dateStr = $d->toDateString();
                $dayCounts = Attendance::query()
                    ->whereDate('date', $d)
                    ->selectRaw('status, count(*) as cnt')
                    ->groupBy('status')
                    ->get()
                    ->keyBy('status');
                $weeklyStatusCounts[] = [
                    'date' => $dateStr,
                    'day' => $d->format('D'),
                    'label' => $d->format('M d'),
                    'present' => (int) (($dayCounts['Present'] ?? null)?->cnt ?? 0),
                    'late' => (int) (($dayCounts['Late'] ?? null)?->cnt ?? 0),
                    'on_leave' => (int) (($dayCounts['Leave'] ?? null)?->cnt ?? 0),
                ];
            }
            $d->addDay();
        }

        return [
            'today_lates' => $todayLates,
            'yesterday_absents' => $yesterdayAbsents,
            'today_leaves' => $todayLeaves,
            'today_status_counts' => $todayStatusCounts,
            'total_employees' => $totalEmployees,
            'monthly_lates' => $monthlyLates,
            'yearly_absents' => $yearlyAbsents,
            'warning_employees' => $warningEmployees,
            'memo_employees' => $memoEmployees,
            'weekly_status_counts' => $weeklyStatusCounts,
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

                    $windowStart = Carbon::createFromFormat('Y-m-d H:i:s', $group['date'].' 06:00:00');
                    $windowEnd = Carbon::createFromFormat('Y-m-d H:i:s', $group['date'].' 20:00:00');

                    $inWindowLogs = collect($group['logs'])
                        ->filter(fn ($scanTime) => $scanTime->betweenIncluded($windowStart, $windowEnd))
                        ->sort();

                    if ($inWindowLogs->isEmpty()) {
                        throw ValidationException::withMessages([
                            'scan_time' => "No biometric scans between 06:00 and 20:00 for {$group['employee_code']} on {$group['date']}.",
                        ]);
                    }

                    if (! $attendance) {
                        // Attendance doesn't exist, create it
                        $attendance = new Attendance;
                        $attendance->employee_id = $employee->id;
                        $attendance->date = $group['date'];

                        $earliestTime = $inWindowLogs->first();
                        $latestTime = $inWindowLogs->last();

                        $attendance->time_in = $earliestTime->format('H:i:s');
                        $attendance->time_out = $latestTime->gt($earliestTime)
                            ? $latestTime->format('H:i:s')
                            : null;

                        $this->updateAttendanceSummary($attendance, $attendanceSetting);
                        $attendance->save();
                        $createdCount++;
                    } else {
                        // Attendance exists, update time_in and time_out from biometric logs if needed
                        $earliestTime = $inWindowLogs->first();
                        $latestTime = $inWindowLogs->last();

                        $attendance->time_in = $earliestTime->format('H:i:s');
                        $attendance->time_out = $latestTime->gt($earliestTime)
                            ? $latestTime->format('H:i:s')
                            : null;

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
        $requiredTimeOut = Carbon::createFromFormat('Y-m-d H:i', $attendance->date.' '.$settings->required_time_out);

        // 🔒 Hard rule: if there is a leave record for this employee+date,
        // treat the day as Leave regardless of any time_in/time_out values.
        $employeeLeave = EmployeeLeave::query()
            ->where('employee_id', $attendance->employee_id)
            ->whereDate('date', $attendance->date)
            ->first();

        if ($employeeLeave) {
            $attendance->status = 'Leave';
            $attendance->remarks = 'On leave';
            $attendance->employee_leave_id = $employeeLeave->id;

            // Clear derived period times and hours so DTR and summaries
            // don't accidentally count this day as worked time.
            $attendance->morning_time_in = null;
            $attendance->morning_time_out = null;
            $attendance->afternoon_time_in = null;
            $attendance->afternoon_time_out = null;
            $attendance->overtime_time_in = null;
            $attendance->overtime_time_out = null;
            $attendance->overtime_hours = null;
            $attendance->total_hours = null;

            return;
        }

        // Calculate period-specific times
        $this->calculatePeriodTimes($attendance, $timeIn, $timeOut, $requiredTimeIn, $requiredTimeOut, $settings);

        if ($timeIn && $timeOut) {
            $attendance->status = $timeIn->greaterThan($requiredTimeIn) ? 'Late' : 'Present';
            $attendance->remarks = 'Complete';
            $hours = $this->calculateTotalHours($timeIn, $timeOut, $requiredTimeIn, $settings);
            $attendance->setAttribute('total_hours', round($hours, 2));

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

    /**
     * Calculate and populate period-specific times (morning, afternoon, overtime)
     */
    private function calculatePeriodTimes(
        Attendance $attendance,
        ?Carbon $timeIn,
        ?Carbon $timeOut,
        Carbon $requiredTimeIn,
        Carbon $requiredTimeOut,
        AttendanceSetting $settings
    ): void {
        // Reset period times
        $attendance->morning_time_in = null;
        $attendance->morning_time_out = null;
        $attendance->afternoon_time_in = null;
        $attendance->afternoon_time_out = null;
        $attendance->overtime_time_in = null;
        $attendance->overtime_time_out = null;
        $attendance->overtime_hours = null;

        if (! $timeIn || ! $timeOut) {
            return;
        }

        // Calculate break end time (lunch break)
        // Assuming break starts 4 hours after required_time_in (e.g., 8 AM + 4 = 12 PM)
        $breakStart = (clone $requiredTimeIn)->addHours(4);
        $breakEnd = (clone $breakStart)->addMinutes($settings->break_duration_minutes);

        // Morning period: from time_in to break start (or time_out if earlier)
        if ($timeIn->lessThanOrEqualTo($breakStart)) {
            $attendance->morning_time_in = $timeIn->format('H:i:s');
            $morningOut = $timeOut->lessThanOrEqualTo($breakStart) ? $timeOut : $breakStart;
            $attendance->morning_time_out = $morningOut->format('H:i:s');
        }

        // Afternoon period: from break end to required_time_out (or time_out if earlier)
        if ($timeOut->greaterThanOrEqualTo($breakEnd)) {
            $afternoonIn = $timeIn->greaterThanOrEqualTo($breakEnd) ? $timeIn : $breakEnd;
            $afternoonOut = $timeOut->lessThanOrEqualTo($requiredTimeOut) ? $timeOut : $requiredTimeOut;

            if ($afternoonIn->lessThan($afternoonOut)) {
                $attendance->afternoon_time_in = $afternoonIn->format('H:i:s');
                $attendance->afternoon_time_out = $afternoonOut->format('H:i:s');
            }
        }

        // Overtime period: from required_time_out to time_out (if time_out is after required_time_out)
        if ($timeOut->greaterThan($requiredTimeOut) && $attendance->is_overtime) {
            $attendance->overtime_time_in = $requiredTimeOut->format('H:i:s');
            $attendance->overtime_time_out = $timeOut->format('H:i:s');

            // Calculate overtime hours
            $overtimeMinutes = $requiredTimeOut->diffInMinutes($timeOut);
            $attendance->setAttribute('overtime_hours', round($overtimeMinutes / 60, 2));
        }
    }

    private function calculateTotalHours(
        Carbon $timeIn,
        Carbon $timeOut,
        Carbon $requiredTimeIn,
        AttendanceSetting $settings
    ): float {
        // Regular hours are capped by the configured workday:
        // - Morning: up to 4 hours from required_time_in
        // - Afternoon: from end of break to required_time_out
        // We deliberately do NOT count overtime here; that is tracked separately.

        // Derive key times from settings
        $requiredStart = $requiredTimeIn->copy();
        $requiredEnd = Carbon::createFromFormat('Y-m-d H:i', $timeIn->format('Y-m-d').' '.$settings->required_time_out);

        // Break starts 4 hours after required_time_in
        $breakStart = $requiredStart->copy()->addHours(4);
        $breakEnd = $breakStart->copy()->addMinutes((int) $settings->break_duration_minutes);

        $totalMinutes = 0;

        // Morning block: from max(timeIn, requiredStart) to min(timeOut, breakStart), max 4h
        if ($timeIn->lessThanOrEqualTo($breakStart) && $timeOut->greaterThan($requiredStart)) {
            $morningIn = $timeIn->greaterThan($requiredStart) ? $timeIn : $requiredStart;
            $morningOut = $timeOut->lessThanOrEqualTo($breakStart) ? $timeOut : $breakStart;
            if ($morningIn->lessThan($morningOut)) {
                $morningMinutes = $morningIn->diffInMinutes($morningOut);
                // Cap at 4 hours (240 minutes) just in case settings are unusual
                $totalMinutes += min($morningMinutes, 240);
            }
        }

        // Afternoon block: from max(timeIn, breakEnd) to min(timeOut, requiredEnd)
        if ($timeOut->greaterThanOrEqualTo($breakEnd)) {
            $afternoonIn = $timeIn->greaterThan($breakEnd) ? $timeIn : $breakEnd;
            $afternoonOut = $timeOut->lessThanOrEqualTo($requiredEnd) ? $timeOut : $requiredEnd;
            if ($afternoonIn->lessThan($afternoonOut)) {
                $afternoonMinutes = $afternoonIn->diffInMinutes($afternoonOut);
                // Cap at 4 hours (240 minutes) to keep total regular hours at max 8
                $totalMinutes += min($afternoonMinutes, 240);
            }
        }

        // We already excluded the break window by splitting morning/afternoon.
        // If break_is_counted is true, we could add it back, but in most HR
        // policies break is not counted, so we leave it as-is.

        return round($totalMinutes / 60, 2);
    }

    public function toggleOvertime(ToggleOvertimeRequest $request, Attendance $attendance): JsonResponse
    {
        $data = $request->validated();

        $attendance->is_overtime = $data['is_overtime'];
        $attendance->save();

        return response()->json([
            'message' => 'Overtime status updated successfully.',
            'attendance' => $this->transformAttendance($attendance->fresh(['employee.department', 'employee.position'])),
        ], \Illuminate\Http\Response::HTTP_OK);
    }

    public function bulkToggleOvertime(BulkToggleOvertimeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $employeeIds = $data['employee_ids'];
        $date = $data['date'];
        $isOvertime = $data['is_overtime'];

        $updatedCount = Attendance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', $date)
            ->update(['is_overtime' => $isOvertime]);

        return response()->json([
            'message' => "Overtime status updated for {$updatedCount} attendance record(s).",
            'updated_count' => $updatedCount,
        ], \Illuminate\Http\Response::HTTP_OK);
    }
}
