<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can create departments
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
            'code' => ['required', 'string', 'max:255', Rule::unique(Department::class)],
            'name' => ['required', 'string', 'max:255', Rule::unique(Department::class)],
        ];
    }
}
