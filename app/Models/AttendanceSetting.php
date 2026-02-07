<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AttendanceSetting extends Model
{
    use HasFactory;

    public const DEFAULT_TIME_IN = '08:00';

    public const DEFAULT_TIME_OUT = '22:00';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'required_time_in',
        'required_time_out',
        'break_duration_minutes',
        'break_is_counted',
        'late_threshold_warning',
        'late_threshold_memo',
        'absent_threshold_warning',
        'absent_threshold_memo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'break_duration_minutes' => 'integer',
            'break_is_counted' => 'boolean',
            'late_threshold_warning' => 'integer',
            'late_threshold_memo' => 'integer',
            'absent_threshold_warning' => 'integer',
            'absent_threshold_memo' => 'integer',
        ];
    }

    /**
     * @return array<string, int|string|bool>
     */
    public static function defaultValues(): array
    {
        return [
            'required_time_in' => self::DEFAULT_TIME_IN,
            'required_time_out' => self::DEFAULT_TIME_OUT,
            'break_duration_minutes' => 0,
            'break_is_counted' => false,
        ];
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function requiredTimeIn(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->formatTimeValue($value),
        );
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function requiredTimeOut(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->formatTimeValue($value),
        );
    }

    private function formatTimeValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        return Carbon::createFromFormat('H:i:s', $value)->format('H:i');
    }
}
