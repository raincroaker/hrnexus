<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Document extends Model
{
    use HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'department_id',
        'file_name',
        'stored_name',
        'mime_type',
        'size',
        'description',
        'content',
        'embedding',
        'accessibility',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_message',
        'restored_by',
        'restored_at',
        'deleted_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'document_tags');
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(DocumentAccessRequest::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(DocumentDownload::class);
    }

    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'reviewed_at' => 'datetime',
            'restored_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // When a document is soft deleted, remove it from Meilisearch
        static::deleting(function (Document $document) {
            if (! $document->isForceDeleting()) {
                // Soft delete - remove from Meilisearch
                $document->unsearchable();
            }
        });

        // When a document is restored, re-index it to Meilisearch if it should be searchable
        static::restored(function (Document $document) {
            // Only re-index if document is approved and has content
            if ($document->status === 'approved' && ! empty($document->content)) {
                $document->searchable();
            }
        });

        // When a document is permanently deleted, ensure it's removed from Meilisearch
        static::forceDeleted(function (Document $document) {
            $document->unsearchable();
        });
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $data = [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'description' => $this->description ?? '',
            'content' => $this->content ?? '', // Empty string for Excel files
            'department_id' => $this->department_id,
            'user_id' => $this->user_id,
            'accessibility' => $this->accessibility,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        // Add embedding as _vectors field for Meilisearch vector search
        // Only add _vectors if embedding exists (Excel files won't have embeddings)
        // Meilisearch expects _vectors to be an object with a key (e.g., "default")
        if (! empty($this->embedding)) {
            $embeddingArray = is_string($this->embedding) ? json_decode($this->embedding, true) : $this->embedding;
            if (is_array($embeddingArray) && ! empty($embeddingArray)) {
                $data['_vectors'] = [
                    'default' => $embeddingArray,
                ];
            }
        }
        // Excel files: no _vectors field (content is empty, so no embedding)

        return $data;
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        // Index approved documents that have either:
        // 1. Content (PDF, Word, PPT with extracted text)
        // 2. Description (Excel files and other files without content extraction)
        $hasContent = ! empty($this->content);
        $hasDescription = ! empty($this->description);
        $excelMimeTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            'application/vnd.ms-excel', // .xls
        ];
        $isExcel = in_array($this->mime_type, $excelMimeTypes);

        // Excel files: index if approved and has description
        if ($isExcel) {
            return $this->status === 'approved' && $hasDescription;
        }

        // Other files: index if approved and has content
        return $this->status === 'approved' && $hasContent;
    }
}
