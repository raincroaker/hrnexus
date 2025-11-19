<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAccessRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'document_id',
        'status',
        'requested_at',
        'reviewed_at',
        'reviewed_by',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
