<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EmployeesController extends Controller
{
    public function index(): \Inertia\Response
    {
        // Get authenticated user
        $user = Auth::user();

        // Find employee record by email (User and Employee are linked by email)
        $currentEmployee = Employee::where('email', $user->email)->first();

        // If no employee record found, return error or handle accordingly
        if (! $currentEmployee) {
            abort(403, 'Employee record not found');
        }

        // Get current user's role and department_id
        $userRole = $currentEmployee->role;
        $userDepartmentId = $currentEmployee->department_id;

        // Filter employees based on role
        $employeesQuery = Employee::with(['department', 'position']);

        if ($userRole === 'admin') {
            // Admin sees all employees
            $employees = $employeesQuery->get();
        } else {
            // Employee and Department Manager see only their department
            $employees = $employeesQuery->where('department_id', $userDepartmentId)->get();
        }

        // Format employees for frontend (include display fields)
        $formattedEmployees = $employees->map(function ($employee) {
            $birthDate = $this->formatDateForResponse($employee->birth_date);
            $hireDate = $this->formatDateForResponse($employee->hire_date);
            $inactiveDate = $this->formatDateForResponse($employee->inactive_date);

            // Format avatar URL
            $avatarUrl = null;
            if ($employee->avatar) {
                $avatarUrl = Storage::url($employee->avatar);
            }

            return [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->full_name, // Uses accessor
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
                'hire_date' => $hireDate,
                'employment_status' => $employee->employment_status ?? 'active',
                'inactive_reason' => $employee->inactive_reason,
                'inactive_reason_notes' => $employee->inactive_reason_notes,
                'inactive_date' => $inactiveDate,
                'length_of_service' => $employee->length_of_service,
                'avatar' => $avatarUrl,
            ];
        });

        // Filter departments based on role
        if ($userRole === 'admin') {
            // Admin sees all departments
            $departments = Department::all();
        } else {
            // Employee and Department Manager see only their department
            $departments = Department::where('id', $userDepartmentId)->get();
        }

        // Format departments for frontend
        $formattedDepartments = $departments->map(function ($department) {
            return [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
            ];
        });

        // Filter positions based on role
        $positionsQuery = Position::with('department');

        if ($userRole === 'admin') {
            // Admin sees all positions
            $positions = $positionsQuery->get();
        } else {
            // Employee and Department Manager see only positions in their department
            $positions = $positionsQuery->where('department_id', $userDepartmentId)->get();
        }

        // Format positions for frontend
        $formattedPositions = $positions->map(function ($position) {
            return [
                'id' => $position->id,
                'name' => $position->name,
                'department' => $position->department?->name ?? '',
                'department_id' => $position->department_id,
            ];
        });

        // Format current user for frontend
        $currentUserBirthDate = $this->formatDateForResponse($currentEmployee->birth_date);
        $currentUserHireDate = $this->formatDateForResponse($currentEmployee->hire_date);
        $currentUserInactiveDate = $this->formatDateForResponse($currentEmployee->inactive_date);

        // Format avatar URL for current user
        $currentUserAvatarUrl = null;
        if ($currentEmployee->avatar) {
            $currentUserAvatarUrl = Storage::url($currentEmployee->avatar);
        }

        $currentUser = [
            'id' => $currentEmployee->id,
            'name' => $currentEmployee->full_name,
            'first_name' => $currentEmployee->first_name,
            'last_name' => $currentEmployee->last_name,
            'employee_code' => $currentEmployee->employee_code,
            'department' => $currentEmployee->department?->name ?? '',
            'department_id' => $currentEmployee->department_id,
            'position' => $currentEmployee->position?->name ?? '',
            'position_id' => $currentEmployee->position_id,
            'role' => $currentEmployee->role,
            'email' => $currentEmployee->email,
            'contact_number' => $currentEmployee->contact_number,
            'birth_date' => $currentUserBirthDate,
            'hire_date' => $currentUserHireDate,
            'employment_status' => $currentEmployee->employment_status ?? 'active',
            'inactive_reason' => $currentEmployee->inactive_reason,
            'inactive_reason_notes' => $currentEmployee->inactive_reason_notes,
            'inactive_date' => $currentUserInactiveDate,
            'length_of_service' => $currentEmployee->length_of_service,
            'avatar' => $currentUserAvatarUrl,
        ];

        return Inertia::render('Employees', [
            'employees' => $formattedEmployees,
            'departments' => $formattedDepartments,
            'positions' => $formattedPositions,
            'currentUser' => $currentUser,
        ]);
    }

    /**
     * Store a newly created employee and user.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $employmentStatus = $validated['employment_status'] ?? 'active';
        $isInactive = $employmentStatus === 'inactive';

        // Handle avatar upload if provided
        $avatarPath = null;
        if (! empty($validated['avatar'])) {
            $avatarPath = $this->handleAvatarUpload($validated['avatar']);
        }

        // Create User account
        $user = User::create([
            'name' => "{$validated['first_name']} {$validated['last_name']}",
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Create Employee record
        $employee = Employee::create([
            'department_id' => $validated['department_id'],
            'position_id' => $validated['position_id'] ?? null,
            'employee_code' => $validated['employee_code'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'contact_number' => $validated['contact_number'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'hire_date' => $validated['hire_date'] ?? null,
            'avatar' => $avatarPath,
            'role' => $validated['role'],
            'employment_status' => $employmentStatus,
            'inactive_reason' => $isInactive ? ($validated['inactive_reason'] ?? null) : null,
            'inactive_reason_notes' => $isInactive ? ($validated['inactive_reason_notes'] ?? null) : null,
            'inactive_date' => $isInactive ? ($validated['inactive_date'] ?? null) : null,
        ]);

        // Load relationships for response
        $employee->load(['department', 'position']);

        // Format for frontend
        $birthDate = $this->formatDateForResponse($employee->birth_date);
        $hireDate = $this->formatDateForResponse($employee->hire_date);
        $inactiveDate = $this->formatDateForResponse($employee->inactive_date);

        // Format avatar URL
        $avatarUrl = null;
        if ($employee->avatar) {
            $avatarUrl = Storage::url($employee->avatar);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully',
            'employee' => [
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
                'hire_date' => $hireDate,
                'employment_status' => $employee->employment_status ?? 'active',
                'inactive_reason' => $employee->inactive_reason,
                'inactive_reason_notes' => $employee->inactive_reason_notes,
                'inactive_date' => $inactiveDate,
                'length_of_service' => $employee->length_of_service,
                'avatar' => $avatarUrl,
            ],
        ], 201);
    }

    /**
     * Update the specified employee and user.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $validated = $request->validated();
        $employmentStatus = $validated['employment_status'] ?? $employee->employment_status ?? 'active';
        $isInactive = $employmentStatus === 'inactive';

        // Get associated user
        $user = User::where('email', $employee->email)->first();

        // Handle avatar upload if provided
        if (! empty($validated['avatar'])) {
            // Only process if it's a Base64 string (new upload)
            // If it's a URL string, it means the avatar wasn't changed, so keep existing
            if (str_starts_with($validated['avatar'], 'data:image/')) {
                // Delete old avatar if exists
                if ($employee->avatar) {
                    Storage::disk('public')->delete($employee->avatar);
                }
                $validated['avatar'] = $this->handleAvatarUpload($validated['avatar']);
            } else {
                // It's a URL string (unchanged avatar), keep existing
                unset($validated['avatar']);
            }
        } else {
            // Keep existing avatar if not provided
            unset($validated['avatar']);
        }

        // Update User account if email or name changed
        if ($user) {
            $userData = [
                'name' => "{$validated['first_name']} {$validated['last_name']}",
                'email' => $validated['email'],
            ];

            // Update password only if provided
            if (! empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);
        }

        // Remove password from validated data before updating employee
        unset($validated['password']);
        $validated['employment_status'] = $employmentStatus;
        if (! $isInactive) {
            $validated['inactive_reason'] = null;
            $validated['inactive_reason_notes'] = null;
            $validated['inactive_date'] = null;
        }

        // Update Employee record
        $employee->update($validated);

        // Load relationships for response
        $employee->load(['department', 'position']);

        // Format for frontend
        $birthDate = $this->formatDateForResponse($employee->birth_date);
        $hireDate = $this->formatDateForResponse($employee->hire_date);
        $inactiveDate = $this->formatDateForResponse($employee->inactive_date);

        // Format avatar URL
        $avatarUrl = null;
        if ($employee->avatar) {
            $avatarUrl = Storage::url($employee->avatar);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully',
            'employee' => [
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
                'hire_date' => $hireDate,
                'employment_status' => $employee->employment_status ?? 'active',
                'inactive_reason' => $employee->inactive_reason,
                'inactive_reason_notes' => $employee->inactive_reason_notes,
                'inactive_date' => $inactiveDate,
                'length_of_service' => $employee->length_of_service,
                'avatar' => $avatarUrl,
            ],
        ]);
    }

    /**
     * Remove the specified employee (soft delete) and permanently delete associated user.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        // Check authorization
        $user = Auth::user();
        $currentEmployee = Employee::where('email', $user->email)->first();

        if (! $currentEmployee) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Employees cannot delete any employee
        if ($currentEmployee->role === 'employee') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Department Manager can only delete employees in their own department
        if ($currentEmployee->role === 'department_manager') {
            if ($currentEmployee->department_id != $employee->department_id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        // Get associated user before soft deleting employee
        $associatedUser = User::where('email', $employee->email)->first();

        // Delete avatar if exists
        if ($employee->avatar) {
            Storage::disk('public')->delete($employee->avatar);
        }

        // Soft delete employee
        $employee->delete();

        // Permanently delete associated user
        if ($associatedUser) {
            $associatedUser->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully',
        ]);
    }

    /**
     * Handle Base64 image upload and return storage path.
     */
    private function handleAvatarUpload(string $base64Image): string
    {
        // Validate Base64 format (data:image/...;base64,...)
        if (! preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
            throw new \InvalidArgumentException('Invalid base64 image format');
        }

        // Extract image type and data
        $imageType = $matches[1];
        $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
        $decodedImage = base64_decode($imageData, true);

        if ($decodedImage === false) {
            throw new \InvalidArgumentException('Failed to decode base64 image');
        }

        // Validate file size (3MB = 3 * 1024 * 1024 bytes)
        $maxSize = 3 * 1024 * 1024;
        if (strlen($decodedImage) > $maxSize) {
            throw new \InvalidArgumentException('Image size exceeds 3MB limit');
        }

        // Generate UUID v4 filename
        $filename = Str::uuid()->toString().'.'.$imageType;

        // Ensure avatars directory exists
        $avatarsDir = 'avatars';
        if (! Storage::disk('public')->exists($avatarsDir)) {
            Storage::disk('public')->makeDirectory($avatarsDir);
        }

        // Store in storage/app/public/avatars/
        $path = $avatarsDir.'/'.$filename;
        Storage::disk('public')->put($path, $decodedImage);

        return $path;
    }

    private function formatDateForResponse($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return null;
    }
}
