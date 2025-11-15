<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    ];

    // Relationships
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    // Accessor for full name
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function biometricLogs()
    {
        return $this->hasMany(BiometricLog::class, 'employee_code', 'employee_code');
    }
}
