<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricLog extends Model
{
    use HasFactory;

    protected $fillable = ['employee_code', 'scan_time'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_code', 'employee_code');
    }
}
