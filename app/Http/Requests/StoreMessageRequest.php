<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
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

        return [
            'content' => [
                'nullable',
                'string',
                'max:10000',
                function ($attribute, $value, $fail) {
                    // If no attachments, content is required
                    if (empty($this->file('attachments')) && empty(trim($value ?? ''))) {
                        $fail('Message content is required when no attachments are provided.');
                    }
                },
            ],
            'attachments' => [
                'nullable',
                'array',
                'max:4',
            ],
            'attachments.*' => [
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
                            $filename = $value->getClientOriginalName();
                            $fail("The file \"{$filename}\" is not allowed. Only PDF, Word, Excel, and PowerPoint files are permitted.");
                        }

                        // Validate MIME type matches extension
                        $extensionToMime = [
                            'pdf' => 'application/pdf',
                            'doc' => 'application/msword',
                            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'xls' => 'application/vnd.ms-excel',
                            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'ppt' => 'application/vnd.ms-powerpoint',
                            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        ];

                        if (isset($extensionToMime[$extension]) && $extensionToMime[$extension] !== $mimeType) {
                            $filename = $value->getClientOriginalName();
                            $fail("The file \"{$filename}\" has an invalid MIME type. The file extension does not match the file content.");
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'attachments.max' => 'You can upload a maximum of 4 files.',
            'attachments.*.max' => 'Each file must not exceed 10MB.',
            'attachments.*.mimes' => 'Only PDF, Word, Excel, and PowerPoint files are allowed.',
        ];
    }
}
