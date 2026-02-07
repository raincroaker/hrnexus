<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = [
        'name',
        'annual_leaves',
    ];

    protected function casts(): array
    {
        return [
            'annual_leaves' => 'integer',
        ];
    }
}
