<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateCalendarEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $employee = Employee::where('email', $user->email)->first();
        if (! $employee) {
            return false;
        }

        $event = $this->route('calendarEvent');

        // Admin can update any event
        if ($employee->role === 'admin') {
            return true;
        }

        // Department managers can only update events they created or events in their department
        if ($employee->role === 'department_manager') {
            return $event->user_id === $user->id ||
                   ($event->visibility === 'department' && $event->department_id === $employee->department_id);
        }

        // Employees cannot update events
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();
        $isAdmin = $employee && $employee->role === 'admin';
        $event = $this->route('calendarEvent');

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:event_categories,id'],
            'visibility' => ['required', 'string', Rule::in(['everyone', 'department'])],
            'is_all_day' => ['required', 'boolean'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
        ];

        // If visibility is 'department', department_id is required
        if ($this->input('visibility') === 'department') {
            $rules['department_id'] = ['required', 'integer', 'exists:departments,id'];
        } else {
            $rules['department_id'] = ['nullable', 'integer', 'exists:departments,id'];
        }

        // Role-based validation: Department managers can only set visibility to their department
        if (! $isAdmin && $employee) {
            $rules['visibility'] = ['required', 'string', Rule::in(['department'])];
            $rules['department_id'] = ['required', 'integer', Rule::in([$employee->department_id])];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Event title is required.',
            'visibility.required' => 'Event visibility is required.',
            'visibility.in' => 'Invalid visibility option. Department managers can only create department-specific events.',
            'department_id.required' => 'Department is required when visibility is set to "Specific Department".',
            'department_id.in' => 'You can only create events for your own department.',
            'start_date.required' => 'Start date is required.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'end_time.after' => 'End time must be after start time.',
        ];
    }
}
