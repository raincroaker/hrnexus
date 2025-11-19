<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PositionsController extends Controller
{
    /**
     * Store a newly created position.
     */
    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = Position::create($request->validated());

        // Load department relationship for response
        $position->load('department');

        return response()->json([
            'message' => 'Position created successfully',
            'position' => [
                'id' => $position->id,
                'name' => $position->name,
                'department' => $position->department?->name ?? '',
                'department_id' => $position->department_id,
            ],
        ], 201);
    }

    /**
     * Update the specified position.
     */
    public function update(UpdatePositionRequest $request, Position $position): JsonResponse
    {
        $position->update($request->validated());

        // Load department relationship for response
        $position->load('department');

        return response()->json([
            'message' => 'Position updated successfully',
            'position' => [
                'id' => $position->id,
                'name' => $position->name,
                'department' => $position->department?->name ?? '',
                'department_id' => $position->department_id,
            ],
        ]);
    }

    /**
     * Remove the specified position.
     */
    public function destroy(Position $position): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $employee = Employee::where('email', $user->email)->first();
        if (! $employee) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Employees cannot delete positions
        if ($employee->role === 'employee') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Department Manager can only delete positions in their own department
        if ($employee->role === 'department_manager') {
            if ($employee->department_id != $position->department_id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        // Check if position has employees
        $employeeCount = $position->employees()->count();
        if ($employeeCount > 0) {
            return response()->json([
                'message' => "Cannot delete position. This position is currently being used by {$employeeCount} employee(s). Please reassign or remove employees before deleting this position.",
            ], 422);
        }

        // Permanently delete the position
        $position->delete();

        return response()->json([
            'message' => 'Position deleted successfully',
        ]);
    }
}
