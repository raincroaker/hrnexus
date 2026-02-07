<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'total_leaves',
        'year',
    ];

    protected function casts(): array
    {
        return [
            'total_leaves' => 'integer',
            'year' => 'integer',
        ];
    }
}
