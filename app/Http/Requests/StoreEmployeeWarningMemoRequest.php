<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeWarningMemoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'type' => ['required', 'string', 'in:warning,memo'],
            'reason_type' => ['required', 'string', 'in:late_warning,late_memo,absent_warning,absent_memo'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'related_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'related_year' => ['required', 'integer', 'min:2020'],
            'count_at_time' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
