<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can update departments
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $employee = \App\Models\Employee::where('email', $user->email)->first();

        return $employee?->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $department = $this->route('department');

        return [
            'code' => ['required', 'string', 'max:255', Rule::unique(Department::class)->ignore($department)],
            'name' => ['required', 'string', 'max:255', Rule::unique(Department::class)->ignore($department)],
        ];
    }
}
