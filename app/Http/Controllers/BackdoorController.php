<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBackdoorDocumentRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\Tag;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        // Fetch all tags for tag management
        $tags = Tag::all();

        // Fetch all departments for filters
        $departments = Department::all();

        return Inertia::render('Backdoor', [
            'documents' => $documents,
            'tags' => $tags,
            'departments' => $departments,
        ]);
    }

    /**
     * Store a new document via backdoor (admin upload with direct content).
     */
    public function store(StoreBackdoorDocumentRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        // Handle file upload
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        // Generate unique stored name
        $storedName = Str::random(40).'.'.$extension;

        // Ensure documents directory exists
        $documentsDir = 'private/documents';
        if (! Storage::disk('local')->exists($documentsDir)) {
            Storage::disk('local')->makeDirectory($documentsDir);
        }

        // Store file
        $storedPath = $file->storeAs($documentsDir, $storedName, 'local');

        // Verify file was stored successfully
        if (! $storedPath || ! Storage::disk('local')->exists($storedPath)) {
            Log::error('File storage failed in backdoor upload', [
                'stored_name' => $storedName,
                'stored_path' => $storedPath,
            ]);

            return response()->json(['message' => 'Failed to store file.'], 500);
        }

        Log::info('File stored successfully in backdoor upload', [
            'stored_name' => $storedName,
            'stored_path' => $storedPath,
            'file_size' => Storage::disk('local')->size($storedPath),
        ]);

        // Create document with approved status (backdoor is admin-only)
        $document = Document::create([
            'user_id' => $user->id,
            'department_id' => $request->input('department_id'),
            'file_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'description' => $request->input('description'),
            'content' => $request->input('content'), // Direct content from user
            'accessibility' => $request->input('accessibility'),
            'status' => 'approved', // Backdoor uploads are auto-approved
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_message' => "File uploaded via backdoor by {$user->name}",
            'extraction_status' => 'completed', // No extraction needed, content provided directly
        ]);

        // Attach tags if provided
        if ($request->has('tags') && is_array($request->input('tags'))) {
            $tagIds = array_filter($request->input('tags', []), 'is_numeric');
            if (! empty($tagIds)) {
                $document->tags()->attach($tagIds);
            }
        }

        // Generate embedding and index if content is provided
        if (! empty($document->content)) {
            try {
                $embeddingService = app(EmbeddingService::class);
                $embedding = $embeddingService->generateEmbedding($document->content);

                if ($embedding) {
                    $document->update([
                        'embedding' => json_encode($embedding),
                    ]);
                    Log::info('Embedding generated for backdoor document', [
                        'document_id' => $document->id,
                        'embedding_dimensions' => count($embedding),
                    ]);
                }

                // Index to Meilisearch
                $document->refresh();
                $document->searchable();
                Log::info('Document indexed to Meilisearch', [
                    'document_id' => $document->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to generate embedding or index document in backdoor', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continue even if embedding fails - document is still created
            }
        }

        // Load relationships for response
        $document->load([
            'user:id,name,email',
            'department:id,name,code',
            'tags:id,name',
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'document' => $document,
        ], 201);
    }
}
