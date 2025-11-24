<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        // Admin and department managers can edit documents
        return $employee && in_array($employee->role, ['admin', 'department_manager']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:5000'],
            'department_id' => [
                'required',
                'integer',
                'exists:departments,id',
            ],
            'accessibility' => [
                'required',
                'string',
                Rule::in(['public', 'private', 'department']),
            ],
            'tags' => ['nullable', 'array'],
            'tags.*' => [
                'required',
                'integer',
                'exists:tags,id',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_id.required' => 'Please select a department.',
            'department_id.exists' => 'The selected department is invalid.',
            'accessibility.required' => 'Please select an access level.',
            'accessibility.in' => 'The access level must be Public, Private, or Department.',
            'tags.*.exists' => 'One or more selected tags are invalid.',
        ];
    }
}
