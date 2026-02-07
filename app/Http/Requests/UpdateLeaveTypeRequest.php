<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveTypeRequest extends FormRequest
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
        $leaveTypeId = $this->route('leaveType')->id;

        return [
            'name' => ['required', 'string', 'max:255', 'unique:leave_types,name,'.$leaveTypeId],
            'annual_leaves' => ['required', 'integer', 'min:0'],
        ];
    }
}
