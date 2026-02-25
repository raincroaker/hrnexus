<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
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

        $employee = \App\Models\Employee::where('email', $user->email)->first();
        if (! $employee) {
            return false;
        }

        // Only admins and department managers can create employees
        return in_array($employee->role, ['admin', 'department_manager']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
                Rule::unique(Employee::class),
            ],
            'employee_code' => ['required', 'string', 'max:255', Rule::unique(Employee::class)],
            'password' => ['required', 'string', 'min:6'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'contact_number' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'hire_date' => ['nullable', 'date'],
            'avatar' => ['nullable', 'string'], // Base64 image string
            'role' => ['required', 'string', Rule::in(['employee', 'department_manager', 'admin'])],
            'employment_status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'inactive_reason' => [
                'nullable',
                'string',
                Rule::in(['terminated', 'resigned', 'retired', 'end_of_contract', 'other']),
                'required_if:employment_status,inactive',
            ],
            'inactive_reason_notes' => ['nullable', 'string', 'max:1000'],
            'inactive_date' => ['nullable', 'date', 'required_if:employment_status,inactive'],
        ];
    }
}
