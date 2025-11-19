<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StorePositionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $employee = Employee::where('email', $user->email)->first();
        if (! $employee) {
            return false;
        }

        // Employees cannot create positions
        if ($employee->role === 'employee') {
            return false;
        }

        // Admin can create positions in any department
        if ($employee->role === 'admin') {
            return true;
        }

        // Department Manager can only create positions in their own department
        if ($employee->role === 'department_manager') {
            $requestedDepartmentId = $this->input('department_id');

            return $employee->department_id == $requestedDepartmentId;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Position::class)->where('department_id', $this->input('department_id')),
            ],
        ];
    }
}
