<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Inertia\Inertia;
use Inertia\Response;

class BackdoorController extends Controller
{
    public function index(): Response
    {
        // Fetch ALL documents including soft deleted ones
        $allDocuments = Document::withTrashed()
            ->with([
                'user:id,name,email',
                'department:id,name,code',
                'tags:id,name',
                'reviewer:id,name,email',
                'deletedByUser:id,name,email',
            ])
            ->latest()
            ->get();

        // Format documents for frontend
        $documents = $allDocuments->map(function ($document) {
            return [
                'id' => $document->id,
                'file_name' => $document->file_name,
                'stored_name' => $document->stored_name,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'description' => $document->description,
                'accessibility' => $document->accessibility,
                'status' => $document->status,
                'user' => $document->user ? [
                    'id' => $document->user->id,
                    'name' => $document->user->name,
                    'email' => $document->user->email,
                ] : null,
                'department' => $document->department ? [
                    'id' => $document->department->id,
                    'name' => $document->department->name,
                    'code' => $document->department->code,
                ] : null,
                'tags' => $document->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                    ];
                }),
                'reviewer' => $document->reviewer ? [
                    'id' => $document->reviewer->id,
                    'name' => $document->reviewer->name,
                    'email' => $document->reviewer->email,
                ] : null,
                'reviewed_at' => $document->reviewed_at?->toDateTimeString(),
                'review_message' => $document->review_message,
                'deleted_at' => $document->deleted_at?->toDateTimeString(),
                'deleted_by_user' => $document->deletedByUser ? [
                    'id' => $document->deletedByUser->id,
                    'name' => $document->deletedByUser->name,
                    'email' => $document->deletedByUser->email,
                ] : null,
                'restored_at' => $document->restored_at?->toDateTimeString(),
                'restored_by' => $document->restored_by,
                'content' => $document->content,
                'created_at' => $document->created_at->toDateTimeString(),
                'updated_at' => $document->updated_at->toDateTimeString(),
            ];
        });

        return Inertia::render('Backdoor', [
            'documents' => $documents,
        ]);
    }
}
