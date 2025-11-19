<?php

namespace App\Models;

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
        'avatar',
        'role',
    ];

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

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function biometricLogs(): HasMany
    {
        return $this->hasMany(BiometricLog::class, 'employee_code', 'employee_code');
    }
}
