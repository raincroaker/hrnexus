<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DepartmentsController extends Controller
{
    /**
     * Store a newly created department.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        return response()->json([
            'message' => 'Department created successfully',
            'department' => [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
            ],
        ], 201);
    }

    /**
     * Update the specified department.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $department->update($request->validated());

        return response()->json([
            'message' => 'Department updated successfully',
            'department' => [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
            ],
        ]);
    }

    /**
     * Remove the specified department.
     */
    public function destroy(Department $department): JsonResponse
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

        // Check if department has employees
        $employeeCount = $department->employees()->count();
        if ($employeeCount > 0) {
            return response()->json([
                'message' => "Cannot delete department. This department is currently being used by {$employeeCount} employee(s). Please reassign or remove employees before deleting this department.",
            ], 422);
        }

        // TODO: Add check for calendar events when implementing calendar events deletion logic
        // For now, hard delete the department (permanent deletion due to unique code constraint)
        $department->forceDelete();

        return response()->json([
            'message' => 'Department deleted successfully',
        ]);
    }
}
