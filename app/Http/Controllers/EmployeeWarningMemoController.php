<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkStoreEmployeeWarningMemoRequest;
use App\Http\Requests\StoreEmployeeWarningMemoRequest;
use App\Http\Requests\UpdateEmployeeWarningMemoRequest;
use App\Models\Employee;
use App\Models\EmployeeAbsentRecord;
use App\Models\EmployeeLateRecord;
use App\Models\EmployeeWarningMemo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeWarningMemoController extends Controller
{
    /**
     * Store a newly created warning/memo.
     */
    public function store(StoreEmployeeWarningMemoRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = Auth::user();

        // Fetch count_at_time if not provided
        $countAtTime = $data['count_at_time'] ?? null;
        if ($countAtTime === null) {
            if (in_array($data['reason_type'], ['late_warning', 'late_memo'])) {
                // Get monthly late count
                $lateRecord = EmployeeLateRecord::query()
                    ->where('employee_id', $data['employee_id'])
                    ->where('month', $data['related_month'])
                    ->where('year', $data['related_year'])
                    ->first();
                $countAtTime = $lateRecord ? $lateRecord->total_lates : 0;
            } elseif (in_array($data['reason_type'], ['absent_warning', 'absent_memo'])) {
                // Get yearly absent count
                $absentRecord = EmployeeAbsentRecord::query()
                    ->where('employee_id', $data['employee_id'])
                    ->where('year', $data['related_year'])
                    ->first();
                $countAtTime = $absentRecord ? $absentRecord->total_absents : 0;
            }
        }

        $warningMemo = EmployeeWarningMemo::create([
            'employee_id' => $data['employee_id'],
            'type' => $data['type'],
            'reason_type' => $data['reason_type'],
            'notes' => $data['notes'] ?? null,
            'sent_by' => $user->id,
            'related_month' => $data['related_month'] ?? null,
            'related_year' => $data['related_year'],
            'count_at_time' => $countAtTime,
        ]);

        return response()->json([
            'message' => ucfirst($data['type']).' sent successfully.',
            'warning_memo' => $this->transformWarningMemo($warningMemo),
        ], 201);
    }

    /**
     * Store multiple warnings/memos at once (bulk send).
     */
    public function bulkStore(BulkStoreEmployeeWarningMemoRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = Auth::user();

        DB::beginTransaction();

        try {
            $created = [];
            foreach ($data['employee_ids'] as $employeeId) {
                // Fetch count_at_time based on reason_type
                $countAtTime = null;
                if (in_array($data['reason_type'], ['late_warning', 'late_memo'])) {
                    // Get monthly late count
                    $lateRecord = EmployeeLateRecord::query()
                        ->where('employee_id', $employeeId)
                        ->where('month', $data['related_month'])
                        ->where('year', $data['related_year'])
                        ->first();
                    $countAtTime = $lateRecord ? $lateRecord->total_lates : 0;
                } elseif (in_array($data['reason_type'], ['absent_warning', 'absent_memo'])) {
                    // Get yearly absent count
                    $absentRecord = EmployeeAbsentRecord::query()
                        ->where('employee_id', $employeeId)
                        ->where('year', $data['related_year'])
                        ->first();
                    $countAtTime = $absentRecord ? $absentRecord->total_absents : 0;
                }

                $warningMemo = EmployeeWarningMemo::create([
                    'employee_id' => $employeeId,
                    'type' => $data['type'],
                    'reason_type' => $data['reason_type'],
                    'notes' => $data['notes'] ?? null,
                    'sent_by' => $user->id,
                    'related_month' => $data['related_month'] ?? null,
                    'related_year' => $data['related_year'],
                    'count_at_time' => $countAtTime,
                ]);
                $created[] = $warningMemo->id;
            }

            DB::commit();

            return response()->json([
                'message' => ucfirst($data['type']).' sent to '.count($created).' employee(s) successfully.',
                'count' => count($created),
                'warning_memos' => EmployeeWarningMemo::whereIn('id', $created)
                    ->with(['employee', 'sentBy'])
                    ->get()
                    ->map(fn ($wm) => $this->transformWarningMemo($wm)),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to send '.$data['type'].'s.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a listing of warnings/memos.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeWarningMemo::query()
            ->with(['employee.department', 'employee.position', 'sentBy', 'resolvedBy']);

        // Filter by employee_id
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by reason_type (supports single value or array)
        if ($request->has('reason_type')) {
            $reasonTypes = $request->input('reason_type');
            if (is_array($reasonTypes)) {
                $query->whereIn('reason_type', $reasonTypes);
            } else {
                $query->where('reason_type', $reasonTypes);
            }
        }

        // Filter by resolved status
        if ($request->has('resolved')) {
            if ($request->input('resolved') === 'true') {
                $query->whereNotNull('resolved_at');
            } else {
                $query->whereNull('resolved_at');
            }
        }

        // Filter by acknowledged status
        if ($request->has('acknowledged')) {
            if ($request->input('acknowledged') === 'true') {
                $query->whereNotNull('acknowledged_at');
            } else {
                $query->whereNull('acknowledged_at');
            }
        }

        // Filter by has reply
        if ($request->has('has_reply')) {
            if ($request->input('has_reply') === 'true') {
                $query->whereNotNull('employee_reply');
            } else {
                $query->whereNull('employee_reply');
            }
        }

        $warningMemos = $query->orderByDesc('created_at')->get();

        return response()->json([
            'warning_memos' => $warningMemos->map(fn ($wm) => $this->transformWarningMemo($wm)),
        ]);
    }

    /**
     * Display the specified warning/memo.
     */
    public function show(EmployeeWarningMemo $warningMemo): JsonResponse
    {
        $warningMemo->load(['employee.department', 'employee.position', 'sentBy', 'resolvedBy']);

        return response()->json([
            'warning_memo' => $this->transformWarningMemo($warningMemo),
        ]);
    }

    /**
     * Check if unresolved warning/memo exists for employee + reason_type + period.
     */
    public function checkExists(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'reason_type' => ['required', 'string', 'in:late_warning,late_memo,absent_warning,absent_memo'],
            'related_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'related_year' => ['required', 'integer', 'min:2020'],
        ]);

        $query = EmployeeWarningMemo::where('employee_id', $request->input('employee_id'))
            ->where('reason_type', $request->input('reason_type'))
            ->where('related_year', $request->input('related_year'))
            ->whereNull('resolved_at'); // Only check unresolved

        if ($request->has('related_month')) {
            $query->where('related_month', $request->input('related_month'));
        } else {
            $query->whereNull('related_month');
        }

        $exists = $query->exists();
        $warningMemo = $exists ? $query->first() : null;

        return response()->json([
            'exists' => $exists,
            'warning_memo' => $warningMemo ? $this->transformWarningMemo($warningMemo) : null,
        ]);
    }

    /**
     * Mark warning/memo as acknowledged (read by employee).
     */
    public function acknowledge(EmployeeWarningMemo $warningMemo): JsonResponse
    {
        if ($warningMemo->acknowledged_at) {
            return response()->json([
                'message' => 'Warning/Memo already acknowledged.',
            ], 422);
        }

        $warningMemo->update([
            'acknowledged_at' => now(),
        ]);

        return response()->json([
            'message' => 'Warning/Memo marked as read.',
            'warning_memo' => $this->transformWarningMemo($warningMemo->fresh()),
        ]);
    }

    /**
     * Add employee reply to warning/memo.
     */
    public function reply(UpdateEmployeeWarningMemoRequest $request, EmployeeWarningMemo $warningMemo): JsonResponse
    {
        $user = Auth::user();
        $currentEmployee = Employee::query()
            ->where('email', $user->email)
            ->first();

        if (! $currentEmployee) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Authorization: Employee can only reply to their own warnings/memos
        if ($currentEmployee->id !== $warningMemo->employee_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($warningMemo->employee_reply) {
            return response()->json([
                'message' => 'Reply already submitted. Editing is not allowed.',
            ], 422);
        }

        $updateData = [
            'employee_reply' => $request->validated()['employee_reply'],
            'replied_at' => now(),
        ];

        // Automatically acknowledge if not already acknowledged
        if (! $warningMemo->acknowledged_at) {
            $updateData['acknowledged_at'] = now();
        }

        $warningMemo->update($updateData);

        return response()->json([
            'message' => 'Reply submitted successfully.',
            'warning_memo' => $this->transformWarningMemo($warningMemo->fresh()),
        ]);
    }

    /**
     * Mark warning/memo as resolved (admin only).
     */
    public function resolve(EmployeeWarningMemo $warningMemo): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee || $employee->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($warningMemo->resolved_at) {
            return response()->json([
                'message' => 'Warning/Memo already resolved.',
            ], 422);
        }

        $warningMemo->update([
            'resolved_at' => now(),
            'resolved_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Warning/Memo marked as resolved.',
            'warning_memo' => $this->transformWarningMemo($warningMemo->fresh(['resolvedBy'])),
        ]);
    }

    /**
     * Export memo as PDF.
     */
    public function exportMemoPdf(EmployeeWarningMemo $warningMemo): \Symfony\Component\HttpFoundation\Response
    {
        $user = Auth::user();
        $currentEmployee = Employee::query()
            ->where('email', $user->email)
            ->first();

        if (! $currentEmployee) {
            abort(403, 'Forbidden');
        }

        // Only memos can be exported, not warnings
        if ($warningMemo->type !== 'memo') {
            abort(400, 'Only memos can be exported as PDF.');
        }

        // Authorization: Employee can download their own memos, admin can download any memo
        $isAuthorized = false;
        if ($currentEmployee->id === $warningMemo->employee_id) {
            $isAuthorized = true;
        } elseif ($currentEmployee->role === 'admin') {
            $isAuthorized = true;
        } elseif ($currentEmployee->role === 'department_manager' &&
                   $currentEmployee->department_id === $warningMemo->employee->department_id) {
            $isAuthorized = true;
        }

        if (! $isAuthorized) {
            abort(403, 'Forbidden');
        }

        // Refresh to ensure we have the latest data
        $warningMemo->refresh();

        // Load relationships
        $warningMemo->load(['employee.department', 'employee.position', 'sentBy', 'resolvedBy']);

        $employee = $warningMemo->employee;
        $sentBy = $warningMemo->sentBy;
        $resolvedBy = $warningMemo->resolvedBy;

        // Determine memo details based on reason_type
        // Use strict comparison and get the actual value
        $reasonType = (string) $warningMemo->reason_type;

        $subjectTitle = '';
        $violationType = '';
        $violationCountText = '';
        $periodText = '';

        if ($reasonType === 'late_memo') {
            $subjectTitle = 'Late Arrival Violation';
            $violationType = 'excessive late arrivals';
            $violationCountText = $warningMemo->count_at_time === 1 ? 'late arrival' : 'late arrivals';
            if ($warningMemo->related_month) {
                $monthName = \Illuminate\Support\Carbon::createFromDate($warningMemo->related_year, $warningMemo->related_month, 1)->format('F');
                $periodText = "for the month of {$monthName} {$warningMemo->related_year}";
            } else {
                $periodText = 'this year';
            }
        } elseif ($reasonType === 'absent_memo') {
            $subjectTitle = 'Absence Violation';
            $violationType = 'excessive absences';
            $violationCountText = $warningMemo->count_at_time === 1 ? 'absence' : 'absences';
            $periodText = "for the year {$warningMemo->related_year}";
        } else {
            // Fallback for unexpected reason_type values
            abort(400, "Invalid memo reason type: {$reasonType}. Expected 'late_memo' or 'absent_memo'.");
        }

        $pdf = Pdf::loadView('memo.pdf', [
            'memo' => $warningMemo,
            'employee' => $employee,
            'sentBy' => $sentBy,
            'resolvedBy' => $resolvedBy,
            'subjectTitle' => $subjectTitle,
            'violationType' => $violationType,
            'violationCountText' => $violationCountText,
            'periodText' => $periodText,
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf(
            'memo-%s-%s.pdf',
            $employee->employee_code ?? $employee->id,
            $warningMemo->created_at->format('Y-m-d')
        );

        return $pdf->stream($fileName);
    }

    /**
     * Transform warning/memo for API response.
     */
    private function transformWarningMemo(EmployeeWarningMemo $warningMemo): array
    {
        return [
            'id' => $warningMemo->id,
            'employee_id' => $warningMemo->employee_id,
            'employee' => $warningMemo->employee ? [
                'id' => $warningMemo->employee->id,
                'employee_code' => $warningMemo->employee->employee_code,
                'name' => $warningMemo->employee->full_name,
                'department' => $warningMemo->employee->department?->name,
                'position' => $warningMemo->employee->position?->name,
            ] : null,
            'type' => $warningMemo->type,
            'reason_type' => $warningMemo->reason_type,
            'notes' => $warningMemo->notes,
            'sent_by' => $warningMemo->sent_by,
            'sent_by_user' => $warningMemo->sentBy ? [
                'id' => $warningMemo->sentBy->id,
                'name' => $warningMemo->sentBy->name,
            ] : null,
            'related_month' => $warningMemo->related_month,
            'related_year' => $warningMemo->related_year,
            'count_at_time' => $warningMemo->count_at_time,
            'acknowledged_at' => $warningMemo->acknowledged_at?->toDateTimeString(),
            'employee_reply' => $warningMemo->employee_reply,
            'replied_at' => $warningMemo->replied_at?->toDateTimeString(),
            'resolved_at' => $warningMemo->resolved_at?->toDateTimeString(),
            'resolved_by' => $warningMemo->resolved_by,
            'resolved_by_user' => $warningMemo->resolvedBy ? [
                'id' => $warningMemo->resolvedBy->id,
                'name' => $warningMemo->resolvedBy->name,
            ] : null,
            'created_at' => $warningMemo->created_at->toDateTimeString(),
            'updated_at' => $warningMemo->updated_at->toDateTimeString(),
        ];
    }
}
