<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Models\DocumentAccessRequest;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class StoreDocumentAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $user = Auth::user();
            $document = $this->route('document');

            if (! $document) {
                $validator->errors()->add('document', 'Document not found.');

                return;
            }

            // Check if user already has access
            if ($this->userHasAccess($user, $document)) {
                $validator->errors()->add('access', 'You already have access to this document.');
            }

            // Check if there's already a pending request
            // Note: Rejected requests are allowed - users can create a new request after rejection
            $existingPendingRequest = DocumentAccessRequest::where('user_id', $user->id)
                ->where('document_id', $document->id)
                ->where('status', 'pending')
                ->first();

            if ($existingPendingRequest) {
                $validator->errors()->add('request', 'You already have a pending request for this document.');
            }

            // Check if there's already an approved request
            // Users with approved requests should not be able to request again
            $existingApprovedRequest = DocumentAccessRequest::where('user_id', $user->id)
                ->where('document_id', $document->id)
                ->where('status', 'approved')
                ->first();

            if ($existingApprovedRequest) {
                $validator->errors()->add('request', 'You already have an approved request for this document.');
            }
        });
    }

    /**
     * Check if user already has access to the document.
     */
    private function userHasAccess($user, Document $document): bool
    {
        // Admin always has access
        $employee = Employee::where('email', $user->email)->first();
        if ($employee && $employee->role === 'admin') {
            return true;
        }

        // Public documents: everyone has access
        if ($document->accessibility === 'public') {
            return true;
        }

        // Check if user is the uploader/owner
        if ($document->user_id === $user->id) {
            return true;
        }

        // Department documents: check if user is in same department
        if ($document->accessibility === 'department' && $employee) {
            if ($document->department_id === $employee->department_id) {
                return true;
            }
        }

        // Private documents: only department managers in same department have access
        if ($document->accessibility === 'private' && $employee) {
            if ($employee->role === 'department_manager' && $document->department_id === $employee->department_id) {
                return true;
            }
        }

        return false;
    }
}
