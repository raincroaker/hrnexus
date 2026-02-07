<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveTypeRequest;
use App\Http\Requests\UpdateLeaveTypeRequest;
use App\Models\EmployeeLeave;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LeaveTypesController extends Controller
{
    /**
     * Store a newly created leave type.
     */
    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        $leaveType = LeaveType::create($request->validated());

        return response()->json([
            'message' => 'Leave type created successfully',
            'leave_type' => [
                'id' => $leaveType->id,
                'name' => $leaveType->name,
                'annual_leaves' => $leaveType->annual_leaves,
            ],
        ], 201);
    }

    /**
     * Update the specified leave type.
     */
    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): JsonResponse
    {
        $leaveType->update($request->validated());

        return response()->json([
            'message' => 'Leave type updated successfully',
            'leave_type' => [
                'id' => $leaveType->id,
                'name' => $leaveType->name,
                'annual_leaves' => $leaveType->annual_leaves,
            ],
        ]);
    }

    /**
     * Remove the specified leave type.
     */
    public function destroy(LeaveType $leaveType): JsonResponse
    {
        // Check if user is admin
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $employee = \App\Models\Employee::where('email', $user->email)->first();
        if (! $employee || $employee->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Check if leave type has employee leave records
        $employeeLeaveCount = EmployeeLeave::where('leave_type_id', $leaveType->id)->count();
        if ($employeeLeaveCount > 0) {
            return response()->json([
                'message' => "Cannot delete leave type. This leave type is currently being used by {$employeeLeaveCount} employee leave record(s). Please remove or reassign these records before deleting this leave type.",
            ], 422);
        }

        $leaveType->delete();

        return response()->json([
            'message' => 'Leave type deleted successfully',
        ]);
    }
}
