<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceSettingRequest extends FormRequest
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
            'required_time_in' => ['required', 'date_format:H:i'],
            'required_time_out' => ['required', 'date_format:H:i', 'after:required_time_in'],
            'break_duration_minutes' => ['required', 'integer', 'min:0'],
            'break_is_counted' => ['required', 'boolean'],
            'late_threshold_warning' => ['sometimes', 'integer', 'min:0'],
            'late_threshold_memo' => ['sometimes', 'integer', 'min:0'],
            'absent_threshold_warning' => ['sometimes', 'integer', 'min:0'],
            'absent_threshold_memo' => ['sometimes', 'integer', 'min:0'],
            'password' => ['required', 'current_password:web'],
        ];
    }
}
