<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveRecord;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeLeavesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaveTypes = LeaveType::query()->pluck('id', 'name');

        if ($leaveTypes->isEmpty()) {
            $this->command?->warn('No leave types found. Run LeaveTypeSeeder first.');

            return;
        }

        $preferredEmployees = [3, 4, 11, 12, 17, 18, 22, 23];
        $employees = Employee::query()
            ->whereIn('id', $preferredEmployees)
            ->orderBy('id')
            ->get();

        if ($employees->isEmpty()) {
            $this->command?->warn('No employees found. Skipping employee leaves seeding.');

            return;
        }

        $year = 2025;
        $dateRange = $this->dateRange();
        $userId = DB::table('users')->value('id');

        if (! $userId) {
            $this->command?->warn('No users found. Unable to set set_by for employee leaves.');

            return;
        }

        // For each targeted employee, create one leave in Nov and one in Dec
        foreach ($employees as $employee) {
            $leaveTypeIdNov = $leaveTypes->values()->random();
            $leaveTypeIdDec = $leaveTypes->values()->random();

            $leaveNov = EmployeeLeave::query()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveTypeIdNov,
                'date' => $dateRange['nov']->random(),
                'notes' => 'Seeded leave entry (Nov)',
                'set_by' => $userId,
            ]);

            $leaveDec = EmployeeLeave::query()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveTypeIdDec,
                'date' => $dateRange['dec']->random(),
                'notes' => 'Seeded leave entry (Dec)',
                'set_by' => $userId,
            ]);

            $this->incrementLeaveRecord($employee->id, $leaveTypeIdNov, $year);
            $this->incrementLeaveRecord($employee->id, $leaveTypeIdDec, $year);
        }
    }

    private function dateRange(): array
    {
        $novStart = Carbon::create(2025, 11, 1);
        $novEnd = Carbon::create(2025, 11, 30);
        $decStart = Carbon::create(2025, 12, 1);
        $decEnd = Carbon::create(2025, 12, 31);

        $novDates = [];
        $cursor = $novStart->copy();
        while ($cursor->lte($novEnd)) {
            $novDates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $decDates = [];
        $cursor = $decStart->copy();
        while ($cursor->lte($decEnd)) {
            $decDates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return [
            'nov' => collect($novDates),
            'dec' => collect($decDates),
        ];
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
}
