<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkToggleOvertimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can bulk toggle overtime
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
        return [
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'is_overtime' => ['required', 'boolean'],
        ];
    }
}
