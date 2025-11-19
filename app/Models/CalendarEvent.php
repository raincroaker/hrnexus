<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'category_id', 'department_id', 'visibility', 'title', 'description', 'location',
        'is_all_day', 'start_date', 'end_date', 'start_time', 'end_time',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, foreignKey: 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class, 'event_id');
    }
}
