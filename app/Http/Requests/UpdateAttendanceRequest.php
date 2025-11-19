<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAttendanceRequest extends FormRequest
{
    private const TIME_IN_START_HOUR = 6;

    private const TIME_IN_END_HOUR = 12;

    private const TIME_OUT_START_HOUR = 12;

    private const TIME_OUT_START_MINUTE = 1;

    private const TIME_OUT_END_HOUR = 19;

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
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $timeIn = $data['time_in'] ?? null;
            $timeOut = $data['time_out'] ?? null;

            // Validate time_in window (06:00 - 12:00)
            if ($timeIn) {
                [$hour, $minute] = explode(':', $timeIn);
                $hour = (int) $hour;
                $minute = (int) $minute;

                $timeInStart = self::TIME_IN_START_HOUR * 60; // 6:00 in minutes
                $timeInEnd = self::TIME_IN_END_HOUR * 60; // 12:00 in minutes
                $timeInMinutes = ($hour * 60) + $minute;

                if ($timeInMinutes < $timeInStart || $timeInMinutes > $timeInEnd) {
                    $validator->errors()->add(
                        'time_in',
                        'Time In must be between 06:00 and 12:00.'
                    );
                }
            }

            // Validate time_out window (12:01 - 19:00)
            if ($timeOut) {
                [$hour, $minute] = explode(':', $timeOut);
                $hour = (int) $hour;
                $minute = (int) $minute;

                $timeOutStart = (self::TIME_OUT_START_HOUR * 60) + self::TIME_OUT_START_MINUTE; // 12:01 in minutes
                $timeOutEnd = self::TIME_OUT_END_HOUR * 60; // 19:00 in minutes
                $timeOutMinutes = ($hour * 60) + $minute;

                if ($timeOutMinutes < $timeOutStart || $timeOutMinutes > $timeOutEnd) {
                    $validator->errors()->add(
                        'time_out',
                        'Time Out must be between 12:01 and 19:00.'
                    );
                }
            }
        });
    }
}
