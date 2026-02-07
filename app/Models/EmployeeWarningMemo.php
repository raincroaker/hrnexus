<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class EmployeeWarningMemo extends Model
{
    protected $table = 'employee_warnings_memos';

    protected $fillable = [
        'employee_id',
        'type',
        'reason_type',
        'notes',
        'sent_by',
        'related_month',
        'related_year',
        'count_at_time',
        'acknowledged_at',
        'employee_reply',
        'replied_at',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'related_month' => 'integer',
            'related_year' => 'integer',
            'count_at_time' => 'integer',
            'acknowledged_at' => 'datetime',
            'replied_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Get the employee that owns the warning/memo.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user (admin) who sent the warning/memo.
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Get the user (admin) who resolved the warning/memo.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope a query to only include active (unresolved) warnings/memos.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope a query to only include unresolved warnings/memos.
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope a query to only include unacknowledged warnings/memos.
     */
    public function scopeUnacknowledged(Builder $query): Builder
    {
        return $query->whereNull('acknowledged_at');
    }

    /**
     * Scope a query to only include acknowledged warnings/memos.
     */
    public function scopeAcknowledged(Builder $query): Builder
    {
        return $query->whereNotNull('acknowledged_at');
    }

    /**
     * Scope a query to only include warnings/memos with employee replies.
     */
    public function scopeWithReplies(Builder $query): Builder
    {
        return $query->whereNotNull('employee_reply');
    }

    /**
     * Scope a query to only include resolved warnings/memos.
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Retrieve the model for bound value.
     * For employee actions (reply), ensure the warning/memo belongs to the current employee.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $warningMemo = $this->where($field ?? $this->getRouteKeyName(), $value)->first();

        if (! $warningMemo) {
            return null;
        }

        // For employee-specific actions, check if the warning/memo belongs to the current employee
        $user = Auth::user();
        if ($user) {
            $currentEmployee = \App\Models\Employee::query()
                ->where('email', $user->email)
                ->first();

            // If current user is an employee (not admin), check ownership
            // Admins can access any warning/memo, so we only check for non-admin employees
            if ($currentEmployee && $currentEmployee->role !== 'admin') {
                if ($warningMemo->employee_id !== $currentEmployee->id) {
                    // Return null to trigger 404, preventing unauthorized access
                    return null;
                }
            }
        }

        return $warningMemo;
    }
}
