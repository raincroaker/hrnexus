<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceSettingRequest extends FormRequest
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
            'required_time_in' => ['sometimes', 'required', 'date_format:H:i'],
            'required_time_out' => ['sometimes', 'required', 'date_format:H:i', 'after:required_time_in'],
            'break_duration_minutes' => ['sometimes', 'required', 'integer', 'min:0'],
            'break_is_counted' => ['sometimes', 'required', 'boolean'],
            'password' => ['required', 'current_password:web'],
        ];
    }
}
