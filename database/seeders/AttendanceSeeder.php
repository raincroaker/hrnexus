<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\EmployeeOvertime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        $employees = Employee::query()
            ->orderBy('id')
            ->limit(25)
            ->get();

        if ($employees->isEmpty()) {
            $this->command?->warn('No employees found. Skipping attendance seeding.');

            return;
        }

        $holidays = DB::table('holidays')->pluck('date')->toArray();
        $leaveMap = $this->leaveMap();

        // Seed from Jan 1, 2026 up to Feb 4, 2026
        $dates = $this->generateDateRange(Carbon::create(2026, 1, 1), Carbon::create(2026, 2, 4));
        $workingDates = $dates->filter(
            fn (Carbon $date) => $date->isSunday() === false && ! in_array($date->toDateString(), $holidays, true)
        );
        $requiredIn = Carbon::parse($setting->required_time_in);
        $requiredOut = Carbon::parse($setting->required_time_out);

        // Demo employees for controlled lates/absents (prefer exact IDs)
        $preferredLate = [5, 10];
        $preferredAbsent = [4, 9];

        $lateEmployees = $employees
            ->pluck('id')
            ->intersect($preferredLate)
            ->values()
            ->all();
        if (empty($lateEmployees)) {
            $lateEmployees = $employees->pluck('id')->take(2)->toArray();
        }

        $absentEmployees = $employees
            ->pluck('id')
            ->intersect($preferredAbsent)
            ->values()
            ->all();
        if (empty($absentEmployees)) {
            $absentEmployees = $employees->pluck('id')->take(2)->toArray();
        }

        // Tracked employees (for leaves / logging) = union of late+absent
        $trackedEmployees = array_values(array_unique(array_merge($lateEmployees, $absentEmployees)));
        if ($this->command && ! empty($trackedEmployees)) {
            $this->command->info('Demo attendance employees (for lates/absents/leaves/overtime): '.implode(', ', $trackedEmployees));
        }

        $janDates = $workingDates->filter(fn (Carbon $d) => $d->month === 1);
        $febDates = $workingDates->filter(fn (Carbon $d) => $d->month === 2);

        // Lates: start with light variety, then override with exact counts for our demo employees
        $lateDatesJan = $this->allocateSpecialDays($janDates, $lateEmployees, 0, 1);
        $lateDatesFeb = $this->allocateSpecialDays($febDates, $lateEmployees, 0, 1);

        // Force exact late counts in February 2026 for testing:
        // - Employee 5: 3 lates in Feb
        // - Employee 10: 4 lates in Feb
        if (! empty($febDates)) {
            // Ensure keys exist as collections
            foreach ($lateEmployees as $empId) {
                if (! isset($lateDatesFeb[$empId])) {
                    $lateDatesFeb[$empId] = collect();
                }
            }

            // Helper closure to safely grab up to N unique Feb working dates
            $takeDates = function (int $skip, int $take) use ($febDates): \Illuminate\Support\Collection {
                return $febDates
                    ->values()
                    ->slice($skip, $take)
                    ->map(fn (Carbon $d) => $d->toDateString());
            };

            // Employee 5: 3 lates
            if (in_array(5, $lateEmployees, true)) {
                $forced5 = $takeDates(0, 3);
                if ($forced5->isNotEmpty()) {
                    $lateDatesFeb[5] = $forced5;
                }
            }

            // Employee 10: 4 lates (try to use later dates to reduce overlap)
            if (in_array(10, $lateEmployees, true)) {
                $forced10 = $takeDates(3, 4);
                if ($forced10->isEmpty()) {
                    // Fallback: just take first 4 if we don't have enough distinct later dates
                    $forced10 = $takeDates(0, 4);
                }
                if ($forced10->isNotEmpty()) {
                    $lateDatesFeb[10] = $forced10;
                }
            }
        }

        // Absents: exact counts for demo employees
        // Initialize empty collections
        $absentDatesJan = [];
        $absentDatesFeb = [];
        foreach ($absentEmployees as $empId) {
            $absentDatesJan[$empId] = collect();
            $absentDatesFeb[$empId] = collect();
        }

        // Force 1 absent day in February 2026 for employees 4 and 9
        if (! empty($febDates)) {
            // Helper closure to pick a single working date
            $pickOne = function (int $skip) use ($febDates): ?string {
                $date = $febDates->values()->slice($skip, 1)->first();
                return $date ? $date->toDateString() : null;
            };

            if (in_array(4, $absentEmployees, true)) {
                $d4 = $pickOne(0);
                if ($d4) {
                    $absentDatesFeb[4] = collect([$d4]);
                }
            }

            if (in_array(9, $absentEmployees, true)) {
                $d9 = $pickOne(1);
                if (! $d9) {
                    $d9 = $pickOne(0);
                }
                if ($d9) {
                    $absentDatesFeb[9] = collect([$d9]);
                }
            }
        }

        // Seed leaves for tracked employees with emphasis on January (for DTR visibility)
        $leaveMap = $this->seedLeaves($lateEmployees, $janDates, $febDates);

        // Collect approved overtime pairs for seeding EmployeeOvertime
        $approvedOvertimePairs = [];
        $overtimeApprovedByUserId = DB::table('users')->orderBy('id')->value('id') ?? 1;

        foreach ($employees as $employee) {
            foreach ($dates as $date) {
                $dateString = $date->toDateString();

                $timeIn = null;
                $timeOut = null;
                $status = 'Present';
                $remarks = 'Complete';
                $employeeLeaveId = null;

                // Holiday
                if (in_array($dateString, $holidays, true)) {
                    $status = 'Holiday';
                    $remarks = 'Holiday';
                }
                // Leave
                elseif (isset($leaveMap[$employee->id][$dateString])) {
                    $status = 'Leave';
                    $remarks = 'On leave';
                    $employeeLeaveId = $leaveMap[$employee->id][$dateString];
                }
                // Absent
                elseif (
                    in_array($employee->id, $absentEmployees, true)
                    && (
                        ($absentDatesJan[$employee->id] ?? collect())->contains($dateString)
                        || ($absentDatesFeb[$employee->id] ?? collect())->contains($dateString)
                    )
                ) {
                    $status = 'Absent';
                    $remarks = 'Absent';
                }
                // Present / Late
                else {
                    $isLateEmployee = in_array($employee->id, $lateEmployees, true)
                        && (
                            ($lateDatesJan[$employee->id] ?? collect())->contains($dateString)
                            || ($lateDatesFeb[$employee->id] ?? collect())->contains($dateString)
                        );

                    $timeIn = (clone $requiredIn)->setDateFrom($date);
                    $timeOut = null;

                    // February 3 (today): no time outs, only time ins
                    $isFeb3 = $dateString === '2026-02-03';

                    if (! $isFeb3) {
                        $timeOut = (clone $requiredOut)->setDateFrom($date);
                    }

                    if ($isLateEmployee) {
                        $timeIn->addMinutes(rand(15, 60));
                    } else {
                        // Non-tracked or non-late days: keep on time or slightly early
                        $timeIn->addMinutes(rand(-5, 0));
                    }

                    if (! $isFeb3 && $timeOut) {
                        $timeOut->addMinutes(rand(-15, 15));
                    }

                    // Explicit overtime candidates for tracked employees on selected days
                    $isOvertimeCandidate = in_array($employee->id, $lateEmployees, true)
                        && (
                            ($lateDatesJan[$employee->id] ?? collect())->contains($dateString)
                            || ($lateDatesFeb[$employee->id] ?? collect())->contains($dateString)
                        );

                    // If this is a designated overtime day, push time_out later to guarantee overtime
                    if ($isOvertimeCandidate && ! $isFeb3 && $timeOut) {
                        $timeOut->addMinutes(rand(30, 120));
                    }

                    if ($timeIn->greaterThan((clone $requiredIn)->setDateFrom($date))) {
                        $status = 'Late';
                    } else {
                        $status = 'Present';
                    }

                    $remarks = $isFeb3 ? 'Missing Time Out' : 'Complete';
                }

                $totalHours = null;

                if ($timeIn && $timeOut) {
                    $totalMinutes = $timeIn->diffInMinutes($timeOut);

                    if (! $setting->break_is_counted) {
                        $totalMinutes = max(0, $totalMinutes - $setting->break_duration_minutes);
                    }

                    $totalHours = round($totalMinutes / 60, 2);
                }

                // Calculate period-specific times
                $requiredTimeInForDate = (clone $requiredIn)->setDateFrom($date);
                $requiredTimeOutForDate = (clone $requiredOut)->setDateFrom($date);
                $periodTimes = $this->calculatePeriodTimes(
                    $timeIn,
                    $timeOut,
                    $requiredTimeInForDate,
                    $requiredTimeOutForDate,
                    $setting
                );

                // Enable overtime when:
                // - Time out is after required_time_out; and
                // - Either this is an explicit overtime candidate for demo employees OR random (~10%) for others
                $isOvertime = $timeIn
                    && $timeOut
                    && $timeOut->greaterThan($requiredTimeOutForDate)
                    && (
                        $isOvertimeCandidate
                        || rand(1, 10) === 1
                    );

                // Only set overtime fields if overtime is enabled
                $overtimeTimeIn = $isOvertime ? $periodTimes['overtime_time_in'] : null;
                $overtimeTimeOut = $isOvertime ? $periodTimes['overtime_time_out'] : null;
                $overtimeHours = $isOvertime ? $periodTimes['overtime_hours'] : null;

                Attendance::query()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'date' => $dateString,
                    ],
                    [
                        'time_in' => $timeIn?->format('H:i:s'),
                        'time_out' => $timeOut?->format('H:i:s'),
                        'morning_time_in' => $periodTimes['morning_time_in'],
                        'morning_time_out' => $periodTimes['morning_time_out'],
                        'afternoon_time_in' => $periodTimes['afternoon_time_in'],
                        'afternoon_time_out' => $periodTimes['afternoon_time_out'],
                        'overtime_time_in' => $overtimeTimeIn,
                        'overtime_time_out' => $overtimeTimeOut,
                        'is_overtime' => $isOvertime,
                        'overtime_hours' => $overtimeHours,
                        'total_hours' => $totalHours,
                        'status' => $status,
                        'remarks' => $remarks,
                        'employee_leave_id' => $employeeLeaveId,
                    ]
                );
                if ($isOvertime) {
                    $approvedOvertimePairs[] = [
                        'employee_id' => $employee->id,
                        'date' => $dateString,
                    ];
                }
            }
        }

        $this->seedLateAndAbsentRecords(
            $lateDatesJan,
            $lateDatesFeb,
            $absentDatesJan,
            $absentDatesFeb
        );

        // Seed EmployeeOvertime records matching seeded overtime attendance
        if (! empty($approvedOvertimePairs)) {
            $uniquePairs = collect($approvedOvertimePairs)
                ->unique(fn (array $pair) => $pair['employee_id'].'|'.$pair['date']);

            foreach ($uniquePairs as $pair) {
                EmployeeOvertime::query()->updateOrCreate(
                    [
                        'employee_id' => $pair['employee_id'],
                        'date' => $pair['date'],
                    ],
                    [
                        'approved_by' => $overtimeApprovedByUserId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function generateDateRange(Carbon $start, Carbon $end): Collection
    {
        $dates = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dates[] = $cursor->copy()->startOfDay();
            $cursor->addDay();
        }

        return collect($dates);
    }

    /**
     * @param  array<int>  $employeeIds
     * @return array<int, Collection<int, string>>
     */
    private function allocateSpecialDays(Collection $dates, array $employeeIds, int $minPerEmployee, int $maxPerEmployee): array
    {
        $result = [];
        foreach ($employeeIds as $employeeId) {
            $count = rand($minPerEmployee, $maxPerEmployee);
            $result[$employeeId] = $dates
                ->shuffle()
                ->take($count)
                ->map(fn (Carbon $date) => $date->toDateString());
        }

        return $result;
    }

    private function seedLateAndAbsentRecords(
        array $lateDatesJan,
        array $lateDatesFeb,
        array $absentDatesJan,
        array $absentDatesFeb
    ): void {
        $lateMonths = [
            1 => $lateDatesJan,
            2 => $lateDatesFeb,
        ];

        foreach ($lateMonths as $month => $datesPerEmployee) {
            foreach ($datesPerEmployee as $employeeId => $dates) {
                DB::table('employee_late_records')->updateOrInsert(
                    [
                        'employee_id' => $employeeId,
                        'month' => $month,
                        'year' => 2026,
                    ],
                    [
                        'total_lates' => $dates->count(),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        $allAbsents = [];
        foreach ([$absentDatesJan, $absentDatesFeb] as $bucket) {
            foreach ($bucket as $employeeId => $dates) {
                $allAbsents[$employeeId] = ($allAbsents[$employeeId] ?? 0) + $dates->count();
            }
        }

        foreach ($allAbsents as $employeeId => $count) {
            DB::table('employee_absent_records')->updateOrInsert(
                [
                    'employee_id' => $employeeId,
                    'year' => 2026,
                ],
                [
                    'total_absents' => $count,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    /**
     * Seed leaves for tracked employees across Jan–Feb 2026 (with emphasis on January) and return a fresh leave map.
     *
     * @param  array<int>  $trackedEmployeeIds
     * @return array<int, array<string, int>>
     */
    private function seedLeaves(array $trackedEmployeeIds, Collection $janDates, Collection $febDates): array
    {
        $leaveTypeIds = DB::table('leave_types')->pluck('id')->toArray();
        $fallbackLeaveTypeId = $leaveTypeIds[0] ?? null;
        $setByUserId = DB::table('users')->orderBy('id')->value('id') ?? 1;

        foreach ($trackedEmployeeIds as $employeeId) {
            $selectedDates = collect();

            if ($janDates->isNotEmpty()) {
                // Up to two January leave days for DTR visibility
                $selectedDates = $selectedDates->merge($janDates->shuffle()->take(2));
            }

            if ($febDates->isNotEmpty()) {
                // Optionally one February leave day for variety
                $selectedDates->push($febDates->random());
            }

            foreach ($selectedDates->unique() as $date) {
                DB::table('employee_leaves')->updateOrInsert(
                    [
                        'employee_id' => $employeeId,
                        'date' => $date->toDateString(),
                    ],
                    [
                        'leave_type_id' => $leaveTypeIds ? $leaveTypeIds[array_rand($leaveTypeIds)] : $fallbackLeaveTypeId,
                        'notes' => 'Seeded leave',
                        'set_by' => $setByUserId,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        return $this->leaveMap();
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function leaveMap(): array
    {
        $map = [];
        $leaves = DB::table('employee_leaves')->select('id', 'employee_id', 'date')->get();

        foreach ($leaves as $leave) {
            $map[$leave->employee_id][$leave->date] = $leave->id;
        }

        return $map;
    }

    /**
     * Calculate period-specific times (morning, afternoon, overtime)
     *
     * @return array<string, string|null>
     */
    private function calculatePeriodTimes(
        ?Carbon $timeIn,
        ?Carbon $timeOut,
        Carbon $requiredTimeIn,
        Carbon $requiredTimeOut,
        AttendanceSetting $settings
    ): array {
        $result = [
            'morning_time_in' => null,
            'morning_time_out' => null,
            'afternoon_time_in' => null,
            'afternoon_time_out' => null,
            'overtime_time_in' => null,
            'overtime_time_out' => null,
            'overtime_hours' => null,
        ];

        if (! $timeIn || ! $timeOut) {
            return $result;
        }

        // Calculate break times
        // Break starts 4 hours after required_time_in (e.g., 8 AM + 4 = 12 PM)
        $breakStart = (clone $requiredTimeIn)->addHours(4);
        $breakEnd = (clone $breakStart)->addMinutes($settings->break_duration_minutes);

        // Morning period: from time_in to break start (or time_out if earlier)
        if ($timeIn->lessThanOrEqualTo($breakStart)) {
            $result['morning_time_in'] = $timeIn->format('H:i:s');
            $morningOut = $timeOut->lessThanOrEqualTo($breakStart) ? $timeOut : $breakStart;
            $result['morning_time_out'] = $morningOut->format('H:i:s');
        }

        // Afternoon period: from break end to required_time_out (or time_out if earlier)
        if ($timeOut->greaterThanOrEqualTo($breakEnd)) {
            $afternoonIn = $timeIn->greaterThanOrEqualTo($breakEnd) ? $timeIn : $breakEnd;
            $afternoonOut = $timeOut->lessThanOrEqualTo($requiredTimeOut) ? $timeOut : $requiredTimeOut;

            if ($afternoonIn->lessThan($afternoonOut)) {
                $result['afternoon_time_in'] = $afternoonIn->format('H:i:s');
                $result['afternoon_time_out'] = $afternoonOut->format('H:i:s');
            }
        }

        // Overtime period: from required_time_out to time_out (if time_out is after required_time_out)
        if ($timeOut->greaterThan($requiredTimeOut)) {
            $result['overtime_time_in'] = $requiredTimeOut->format('H:i:s');
            $result['overtime_time_out'] = $timeOut->format('H:i:s');

            // Calculate overtime hours
            $overtimeMinutes = $requiredTimeOut->diffInMinutes($timeOut);
            $result['overtime_hours'] = round($overtimeMinutes / 60, 2);
        }

        return $result;
    }
}
