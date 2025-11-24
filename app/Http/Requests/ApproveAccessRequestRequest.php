<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class ApproveAccessRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        // Only admin and department managers can approve access requests
        if (! $employee || ! in_array($employee->role, ['admin', 'department_manager'])) {
            return false;
        }

        $accessRequest = $this->route('accessRequest');

        // Admin can approve any request
        if ($employee->role === 'admin') {
            return true;
        }

        // Department Manager can only approve requests for documents in their department
        if ($employee->role === 'department_manager' && $accessRequest) {
            $document = $accessRequest->document;
            if ($document && $document->department_id === $employee->department_id) {
                return true;
            }
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
            'review_message' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $accessRequest = $this->route('accessRequest');

            if (! $accessRequest) {
                $validator->errors()->add('accessRequest', 'Access request not found.');

                return;
            }

            // Only pending requests can be approved
            if ($accessRequest->status !== 'pending') {
                $validator->errors()->add('status', 'Only pending access requests can be approved.');
            }
        });
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'review_message.max' => 'The review message must not exceed 5000 characters.',
        ];
    }
}
