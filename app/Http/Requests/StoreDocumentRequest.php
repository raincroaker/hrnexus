<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();
        $isAdmin = $employee && $employee->role === 'admin';

        return [
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB in kilobytes
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx',
                function ($attribute, $value, $fail) use ($allowedMimeTypes) {
                    if ($value && $value->isValid()) {
                        $mimeType = $value->getMimeType();
                        $extension = strtolower($value->getClientOriginalExtension());

                        // Validate actual MIME type matches allowed list
                        if (! in_array($mimeType, $allowedMimeTypes)) {
                            $fail('The file must be a PDF, Word, Excel, or PowerPoint document.');
                        }

                        // Additional extension check
                        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
                        if (! in_array($extension, $allowedExtensions)) {
                            $fail('The file must be a PDF, Word, Excel, or PowerPoint document.');
                        }
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'department_id' => [
                Rule::requiredIf($isAdmin),
                'nullable',
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
            'file.required' => 'Please select a file to upload.',
            'file.max' => 'The file size must not exceed 10MB.',
            'file.mimes' => 'The file must be a PDF, Word, Excel, or PowerPoint document.',
            'department_id.required' => 'Please select a department.',
            'department_id.exists' => 'The selected department is invalid.',
            'accessibility.required' => 'Please select an access level.',
            'accessibility.in' => 'The access level must be Public, Private, or Department.',
            'tags.*.exists' => 'One or more selected tags are invalid.',
        ];
    }
}
