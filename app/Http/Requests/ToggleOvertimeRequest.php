<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToggleOvertimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can toggle overtime
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
            'is_overtime' => ['required', 'boolean'],
        ];
    }
}
