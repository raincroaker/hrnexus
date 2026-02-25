<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department_id',
        'position_id',
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'contact_number',
        'birth_date',
        'hire_date',
        'avatar',
        'role',
        'employment_status',
        'inactive_reason',
        'inactive_reason_notes',
        'inactive_date',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'inactive_date' => 'date',
        ];
    }

    // Relationships
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    // Accessor for full name
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getLengthOfServiceAttribute(): string
    {
        if (! $this->hire_date) {
            return 'N/A';
        }

        $start = Carbon::parse($this->hire_date)->startOfDay();
        $end = ($this->employment_status === 'inactive' && $this->inactive_date)
            ? Carbon::parse($this->inactive_date)->startOfDay()
            : now()->startOfDay();

        if ($end->lt($start)) {
            return 'N/A';
        }

        $diff = $start->diff($end);
        $parts = [];
        if ($diff->y > 0) {
            $parts[] = $diff->y.' year'.($diff->y > 1 ? 's' : '');
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m.' month'.($diff->m > 1 ? 's' : '');
        }

        if (empty($parts)) {
            $parts[] = $diff->d.' day'.($diff->d > 1 ? 's' : '');
        }

        return implode(', ', $parts);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function biometricLogs(): HasMany
    {
        return $this->hasMany(BiometricLog::class, 'employee_code', 'employee_code');
    }

    public function employeeOvertime(): HasMany
    {
        return $this->hasMany(EmployeeOvertime::class);
    }
}
