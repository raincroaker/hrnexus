<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveDocumentRequest;
use App\Http\Requests\RejectDocumentRequest;
use App\Http\Requests\StoreDocumentAccessRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Jobs\ExtractPdfContentJob;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAccessRequest;
use App\Models\Employee;
use App\Models\Tag;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Meilisearch\Client;

class DocumentsController extends Controller
{
    private const PDF_MIME_TYPE = 'application/pdf';

    private const DESCRIPTION_ONLY_MIME_TYPES = [
        // Excel files
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        // Word files (require manual summary - OpenAI Chat API doesn't support them)
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        // PowerPoint files (require manual summary - OpenAI Chat API doesn't support them)
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    public function index(): Response
    {
        // Fetch all non-deleted documents with relationships (no filtering - frontend handles permissions)
        // SoftDeletes trait automatically excludes soft-deleted documents, but we make it explicit
        $documents = Document::query()
            ->whereNull('deleted_at') // Explicitly exclude soft-deleted documents
            ->with([
                'user:id,name,email',
                'department:id,name,code',
                'tags:id,name',
                'reviewer:id,name,email',
                'accessRequests' => function ($query) {
                    $query->with(['requester:id,name,email', 'reviewer:id,name,email']);
                },
                'downloads' => function ($query) {
                    $query->with('user:id,name,email');
                },
            ])
            ->latest()
            ->get();

        // Load employees for all downloads (linked by email)
        $allUserEmails = $documents->flatMap(function ($doc) {
            return $doc->downloads->pluck('user.email')->filter();
        })->unique();

        $employeesByEmail = Employee::whereIn('email', $allUserEmails)
            ->with('department:id,name,code')
            ->get()
            ->keyBy('email');

        // Attach employees to downloads
        foreach ($documents as $document) {
            foreach ($document->downloads as $download) {
                if ($download->user && isset($employeesByEmail[$download->user->email])) {
                    $download->setRelation('employee', $employeesByEmail[$download->user->email]);
                }
            }
        }

        // Get current user's employee data (needed for trashed documents filtering)
        $user = Auth::user();
        $currentEmployee = null;
        if ($user) {
            $currentEmployee = Employee::where('email', $user->email)
                ->with('department:id,name,code')
                ->first();
        }

        // Fetch trashed documents separately based on user role
        $trashedDocuments = collect();
        if ($currentEmployee) {
            if ($currentEmployee->role === 'admin') {
                // Admin: all trashed documents
                $trashedDocuments = Document::onlyTrashed()
                    ->with([
                        'user:id,name,email',
                        'department:id,name,code',
                        'tags:id,name',
                    ])
                    ->with(['deletedByUser:id,name,email'])
                    ->latest('deleted_at')
                    ->get();
            } elseif ($currentEmployee->role === 'department_manager' && $currentEmployee->department_id) {
                // Department Manager: only their department's trashed documents
                $trashedDocuments = Document::onlyTrashed()
                    ->where('department_id', $currentEmployee->department_id)
                    ->with([
                        'user:id,name,email',
                        'department:id,name,code',
                        'tags:id,name',
                    ])
                    ->with(['deletedByUser:id,name,email'])
                    ->latest('deleted_at')
                    ->get();
            }
            // Employee: no trashed documents (empty collection)
        }

        // Fetch all tags for tag management
        $tags = Tag::all();

        // Fetch all departments for filters
        $departments = Department::all();

        // Fetch all access requests with relationships
        $accessRequests = DocumentAccessRequest::query()
            ->with([
                'requester:id,name,email',
                'reviewer:id,name,email',
                'document' => function ($query) {
                    $query->select('id', 'file_name', 'mime_type', 'accessibility', 'department_id', 'user_id', 'size')
                        ->with([
                            'department:id,name,code',
                            'user:id,name,email',
                            'tags:id,name',
                        ]);
                },
            ])
            ->latest()
            ->get();

        // Ensure all document attributes are included (including extraction_status, restored_by, deleted_by)
        // Explicitly map to ensure all fields are serialized by Inertia
        $documentsArray = $documents->map(function ($document) {
            $docArray = $document->toArray();
            // Explicitly ensure these fields are included (they might be null but should still be present)
            $docArray['extraction_status'] = $document->extraction_status;
            $docArray['restored_by'] = $document->restored_by;
            $docArray['deleted_by'] = $document->deleted_by;
            $docArray['restored_at'] = $document->restored_at?->toDateTimeString();

            return $docArray;
        })->all();

        // Log the count to debug the discrepancy
        Log::info('DocumentsController@index: Returning documents to frontend', [
            'total_documents_in_db' => Document::whereNull('deleted_at')->count(),
            'documents_collection_count' => $documents->count(),
            'documents_array_count' => count($documentsArray),
            'first_5_ids' => collect($documentsArray)->take(5)->pluck('id')->toArray(),
            'last_5_ids' => collect($documentsArray)->take(-5)->pluck('id')->toArray(),
        ]);

        return Inertia::render('Documents', [
            'documents' => $documentsArray,
            'trashedDocuments' => $trashedDocuments,
            'tags' => $tags,
            'departments' => $departments,
            'accessRequests' => $accessRequests,
            'currentUser' => $currentEmployee ? [
                'id' => $currentEmployee->id,
                'name' => $currentEmployee->full_name,
                'first_name' => $currentEmployee->first_name,
                'last_name' => $currentEmployee->last_name,
                'employee_code' => $currentEmployee->employee_code,
                'department' => $currentEmployee->department?->name ?? '',
                'department_id' => $currentEmployee->department_id,
                'position' => $currentEmployee->position?->name ?? '',
                'position_id' => $currentEmployee->position_id,
                'role' => $currentEmployee->role,
                'email' => $currentEmployee->email,
                'contact_number' => $currentEmployee->contact_number,
                'birth_date' => $currentEmployee->birth_date,
                'avatar' => $currentEmployee->avatar,
            ] : null,
        ]);
    }

    /**
     * Store a newly created document.
     */
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 403);
        }

        // Determine department_id based on role
        $departmentId = null;
        if ($employee->role === 'admin') {
            $departmentId = $request->input('department_id');
        } else {
            // Department Manager and Employee: use their department
            $departmentId = $employee->department_id;
        }

        // Handle file upload
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        Log::info('[Documents] upload_started', [
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        // Generate unique stored name
        $storedName = Str::random(40).'.'.$extension;

        // Store under 'documents' only (disk root is already storage/app/private)
        // Full path = storage/app/private/documents/filename (no double/triple "private")
        $documentsDir = 'documents';
        if (! Storage::disk('local')->exists($documentsDir)) {
            Storage::disk('local')->makeDirectory($documentsDir);
        }

        $storedPath = $file->storeAs($documentsDir, $storedName, 'local');

        // Verify file was stored successfully
        if (! $storedPath || ! Storage::disk('local')->exists($storedPath)) {
            Log::error('[Documents] upload_failed (file_storage_failed)', [
                'stored_name' => $storedName,
                'stored_path' => $storedPath,
                'documents_dir' => $documentsDir,
                'disk_root' => Storage::disk('local')->path(''),
                'expected_full_path' => Storage::disk('local')->path($storedPath),
            ]);

            return response()->json(['message' => 'Failed to store file.'], 500);
        }

        Log::info('[Documents] file_stored', [
            'stored_name' => $storedName,
            'stored_path' => $storedPath,
            'documents_dir' => $documentsDir,
            'file_size' => Storage::disk('local')->size($storedPath),
            'full_path' => Storage::disk('local')->path($storedPath),
            'disk_root' => Storage::disk('local')->path(''),
        ]);

        // Determine status and review information based on role
        $status = 'pending';
        $reviewedBy = null;
        $reviewedAt = null;
        $reviewMessage = null;

        if ($employee->role === 'admin') {
            $status = 'approved';
            $reviewedBy = $user->id;
            $reviewedAt = now();
            $reviewMessage = "File uploaded by {$employee->full_name} - Admin";
        } elseif ($employee->role === 'department_manager') {
            $status = 'approved';
            $reviewedBy = $user->id;
            $reviewedAt = now();
            $reviewMessage = "File uploaded by {$employee->full_name} - Department Manager";
        }

        // Create document (store exact path from storeAs so queue worker finds the file)
        $document = Document::create([
            'user_id' => $user->id,
            'department_id' => $departmentId,
            'file_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'stored_path' => $storedPath,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'description' => $request->input('description'),
            'accessibility' => $request->input('accessibility'),
            'status' => $status,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => $reviewedAt,
            'review_message' => $reviewMessage,
        ]);

        // Attach tags if provided
        if ($request->has('tags') && is_array($request->input('tags'))) {
            $tagIds = array_filter($request->input('tags', []), 'is_numeric');
            if (! empty($tagIds)) {
                $document->tags()->attach($tagIds);
            }
        }

        $isManualSummaryFile = $this->isDescriptionOnlyMime($document->mime_type);
        $manualKeywords = $this->normalizeManualKeywords($request->input('manual_keywords', []));

        if ($isManualSummaryFile) {
            $manualContent = $this->buildManualContent($document->description, $manualKeywords);
            if ($manualContent) {
                $document->update(['content' => $manualContent]);
                $document->refresh();
            }
        }

        // Dispatch extraction job for approved PDF files only (async)
        // Word/PPT files require manual summary (OpenAI Chat API doesn't support them)
        $isPdf = $document->mime_type === self::PDF_MIME_TYPE;

        if ($status === 'approved' && $isPdf) {
            $filePath = $document->stored_path ?? 'documents/'.$document->stored_name;

            // Verify file exists before dispatching job
            if (! Storage::disk('local')->exists($filePath)) {
                Log::error('[Documents] upload_failed (file_not_found_before_dispatch)', [
                    'document_id' => $document->id,
                    'stored_name' => $document->stored_name,
                    'file_path' => $filePath,
                    'full_path' => Storage::disk('local')->path($filePath),
                    'disk_root' => Storage::disk('local')->path(''),
                    'stored_path_from_upload' => $storedPath, // What storeAs returned
                    'all_files' => Storage::disk('local')->allFiles('documents'),
                ]);

                return response()->json([
                    'message' => 'File was uploaded but could not be found for processing.',
                    'document' => $document,
                ], 500);
            }

            Log::info('[Documents] extraction_job_dispatched', [
                'document_id' => $document->id,
                'file_name' => $document->file_name,
                'mime_type' => $document->mime_type,
                'file_path' => $filePath,
                'file_exists' => true,
                'file_size' => Storage::disk('local')->size($filePath),
            ]);

            // Set initial extraction status
            $document->update(['extraction_status' => 'pending']);

            // Dispatch job to queue
            ExtractPdfContentJob::dispatch($document->id);
        }

        if ($isManualSummaryFile && $status === 'approved' && ! empty($document->content)) {
            $this->generateEmbeddingAndIndex($document);
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

    /**
     * Get all documents (API endpoint for refetching).
     */
    public function indexApi(): JsonResponse
    {
        $documents = Document::query()
            ->whereNull('deleted_at') // Explicitly exclude soft-deleted documents
            ->with([
                'user:id,name,email',
                'department:id,name,code',
                'tags:id,name',
                'reviewer:id,name,email',
                'accessRequests' => function ($query) {
                    $query->with(['requester:id,name,email', 'reviewer:id,name,email']);
                },
                'downloads' => function ($query) {
                    $query->with('user:id,name,email');
                },
            ])
            ->latest()
            ->get();

        // Load employees for all downloads (linked by email)
        $allUserEmails = $documents->flatMap(function ($doc) {
            return $doc->downloads->pluck('user.email')->filter();
        })->unique();

        $employeesByEmail = Employee::whereIn('email', $allUserEmails)
            ->with('department:id,name,code')
            ->get()
            ->keyBy('email');

        // Attach employees to downloads
        foreach ($documents as $document) {
            foreach ($document->downloads as $download) {
                if ($download->user && isset($employeesByEmail[$download->user->email])) {
                    $download->setRelation('employee', $employeesByEmail[$download->user->email]);
                }
            }
        }

        // Fetch trashed documents separately based on user role
        $user = Auth::user();
        $currentEmployee = null;
        $trashedDocuments = collect();
        if ($user) {
            $currentEmployee = Employee::where('email', $user->email)
                ->with('department:id,name,code')
                ->first();

            if ($currentEmployee) {
                if ($currentEmployee->role === 'admin') {
                    // Admin: all trashed documents
                    $trashedDocuments = Document::onlyTrashed()
                        ->with([
                            'user:id,name,email',
                            'department:id,name,code',
                            'tags:id,name',
                        ])
                        ->with(['deletedByUser:id,name,email'])
                        ->latest('deleted_at')
                        ->get();
                } elseif ($currentEmployee->role === 'department_manager' && $currentEmployee->department_id) {
                    // Department Manager: only their department's trashed documents
                    $trashedDocuments = Document::onlyTrashed()
                        ->where('department_id', $currentEmployee->department_id)
                        ->with([
                            'user:id,name,email',
                            'department:id,name,code',
                            'tags:id,name',
                        ])
                        ->with(['deletedByUser:id,name,email'])
                        ->latest('deleted_at')
                        ->get();
                }
                // Employee: no trashed documents (empty collection)
            }
        }

        // Fetch all access requests with relationships
        $accessRequests = DocumentAccessRequest::query()
            ->with([
                'requester:id,name,email',
                'reviewer:id,name,email',
                'document' => function ($query) {
                    $query->select('id', 'file_name', 'mime_type', 'accessibility', 'department_id', 'user_id', 'size')
                        ->with([
                            'department:id,name,code',
                            'user:id,name,email',
                            'tags:id,name',
                        ]);
                },
            ])
            ->latest()
            ->get();

        // Ensure all document attributes are included (including extraction_status, restored_by, deleted_by)
        // Explicitly map to ensure all fields are serialized
        $documentsArray = $documents->map(function ($document) {
            $docArray = $document->toArray();
            // Explicitly ensure these fields are included
            $docArray['extraction_status'] = $document->extraction_status;
            $docArray['restored_by'] = $document->restored_by;
            $docArray['deleted_by'] = $document->deleted_by;
            $docArray['restored_at'] = $document->restored_at?->toDateTimeString();

            return $docArray;
        })->all();

        // Log the count to debug the discrepancy
        Log::info('DocumentsController@indexApi: Returning documents to frontend', [
            'total_documents_in_db' => Document::whereNull('deleted_at')->count(),
            'documents_collection_count' => $documents->count(),
            'documents_array_count' => count($documentsArray),
            'first_5_ids' => collect($documentsArray)->take(5)->pluck('id')->toArray(),
            'last_5_ids' => collect($documentsArray)->take(-5)->pluck('id')->toArray(),
        ]);

        return response()->json([
            'documents' => $documentsArray,
            'trashedDocuments' => $trashedDocuments,
            'accessRequests' => $accessRequests,
        ]);
    }

    /**
     * Search documents using Meilisearch (keyword search).
     */
    public function search(Request $request): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 403);
        }

        $query = $request->input('q', '');
        $mode = $request->input('mode', 'keywords'); // 'keywords' or 'context'

        if (empty(trim($query))) {
            return response()->json([
                'results' => [],
                'message' => 'Search query is required.',
            ], 422);
        }

        // For keyword search, use Meilisearch
        if ($mode === 'keywords') {
            // Search using Laravel Scout - only filter by approved status, limit to top 20 by relevance
            // Scout get() uses the model's query (SoftDeletes scope excludes trashed); filter again to be safe
            $searchResults = Document::search($query)
                ->where('status', 'approved')
                ->take(20)
                ->get()
                ->filter(fn (Document $doc) => $doc->deleted_at === null)
                ->values();

            // Load relationships for results
            $searchResults->load([
                'user:id,name,email',
                'department:id,name,code',
                'tags:id,name',
            ]);

            // Return documents in format that transformDocument expects
            $results = $searchResults->map(function ($document) {
                return $document;
            })->values();

            return response()->json([
                'results' => $results,
                'count' => $results->count(),
            ]);
        }

        // For context search, use vector/semantic search with embeddings
        if ($mode === 'context') {
            try {
                // Generate embedding for the search query
                $embeddingService = app(EmbeddingService::class);
                $queryEmbedding = $embeddingService->generateEmbedding($query);

                if (empty($queryEmbedding)) {
                    Log::warning('Failed to generate embedding for context search', [
                        'query' => $query,
                    ]);

                    return response()->json([
                        'results' => [],
                        'message' => 'Failed to process search query. Please try again.',
                    ], 500);
                }

                // Use Meilisearch client directly for vector search
                $meilisearchHost = config('scout.meilisearch.host');
                $meilisearchKey = config('scout.meilisearch.key');
                $client = new Client($meilisearchHost, $meilisearchKey);
                $index = $client->index('documents');

                // Ensure embedder is configured for user-provided embeddings
                $embedders = $index->getEmbedders();
                if (empty($embedders) || ! isset($embedders['default'])) {
                    Log::info('Configuring embedder for user-provided embeddings');
                    $index->updateEmbedders([
                        'default' => [
                            'source' => 'userProvided',
                            'dimensions' => 3072, // text-embedding-3-large dimensions
                        ],
                    ]);
                    // Wait a moment for embedder to be configured
                    sleep(1);
                }

                // Perform vector search: top 10 by semantic relevance only
                // For user-provided embeddings, we use hybrid with semanticRatio 1.0 (pure vector search)
                $vectorSearchResults = $index->search('', [
                    'hybrid' => [
                        'embedder' => 'default',
                        'semanticRatio' => 1.0, // 100% semantic (pure vector search)
                    ],
                    'vector' => $queryEmbedding, // Pass vector as array
                    'filter' => ['status = approved'],
                    'limit' => 10,
                ]);

                // Extract hits and relevance scores from Meilisearch results
                $hits = [];
                if (is_array($vectorSearchResults)) {
                    $hits = $vectorSearchResults;
                } elseif (is_object($vectorSearchResults)) {
                    // Use reflection to access private hits property
                    $reflection = new \ReflectionClass($vectorSearchResults);
                    if ($reflection->hasProperty('hits')) {
                        $property = $reflection->getProperty('hits');
                        $property->setAccessible(true);
                        $hits = $property->getValue($vectorSearchResults) ?? [];
                    }
                }

                if (empty($hits)) {
                    return response()->json([
                        'results' => [],
                        'count' => 0,
                    ]);
                }

                // Extract document IDs from hits
                $documentIds = collect($hits)->pluck('id')->filter()->toArray();

                if (empty($documentIds)) {
                    return response()->json([
                        'results' => [],
                        'count' => 0,
                    ]);
                }

                // Load documents from database with relationships
                $searchResults = Document::whereIn('id', $documentIds)
                    ->where('status', 'approved')
                    ->with([
                        'user:id,name,email',
                        'department:id,name,code',
                        'tags:id,name',
                    ])
                    ->get();

                // Sort by Meilisearch relevance order
                $sortedResults = $searchResults->sortBy(function ($document) use ($documentIds) {
                    return array_search($document->id, $documentIds);
                })->values();

                Log::info('Context search completed', [
                    'query' => $query,
                    'results_count' => $sortedResults->count(),
                ]);

                return response()->json([
                    'results' => $sortedResults,
                    'count' => $sortedResults->count(),
                ]);

            } catch (\Exception $e) {
                Log::error('Context search failed', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'results' => [],
                    'message' => 'Context search failed. Please try again.',
                ], 500);
            }
        }

        return response()->json([
            'results' => [],
            'message' => 'Invalid search mode.',
        ], 422);
    }

    /**
     * Update the specified document.
     */
    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        // Update document fields
        $document->update([
            'description' => $request->input('description'),
            'department_id' => $request->input('department_id'),
            'accessibility' => $request->input('accessibility'),
        ]);

        // Sync tags
        if ($request->has('tags') && is_array($request->input('tags'))) {
            $tagIds = array_filter($request->input('tags', []), 'is_numeric');
            $document->tags()->sync($tagIds);
        } else {
            // If tags array is not provided or empty, remove all tags
            $document->tags()->detach();
        }

        // Load relationships for response
        $document->load([
            'user:id,name,email',
            'department:id,name,code',
            'tags:id,name',
        ]);

        return response()->json([
            'message' => 'Document updated successfully.',
            'document' => $document,
        ]);
    }

    /**
     * Update the content of a document (backdoor access).
     */
    public function updateContent(Request $request, Document $document): JsonResponse
    {
        $request->validate([
            'content' => ['nullable', 'string', 'max:50000'], // Match upload validation limit
        ]);

        $document->update([
            'content' => $request->input('content'),
        ]);

        // Regenerate embedding and re-index if content is provided
        if (! empty($document->content)) {
            try {
                $this->generateEmbeddingAndIndex($document);
                Log::info('Content updated, embedding regenerated and document re-indexed', [
                    'document_id' => $document->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to regenerate embedding or re-index document after content update', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continue even if embedding fails - content is still updated
            }
        } else {
            // If content is empty, remove from search index
            $document->unsearchable();
            Log::info('Content cleared, document removed from search index', [
                'document_id' => $document->id,
            ]);
        }

        // Load relationships for response
        $document->refresh();
        $document->load([
            'user:id,name,email',
            'department:id,name,code',
            'tags:id,name',
        ]);

        return response()->json([
            'message' => 'Document content updated successfully.',
            'document' => $document,
        ]);
    }

    /**
     * Delete (soft delete) the specified document.
     */
    public function destroy(Request $request, Document $document): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 403);
        }

        // Admin and department managers can delete documents
        if (! in_array($employee->role, ['admin', 'department_manager'])) {
            return response()->json(['message' => 'You do not have permission to delete documents.'], 403);
        }

        // Validate password
        $password = $request->input('password');
        if (empty($password)) {
            return response()->json(['message' => 'Password is required to confirm deletion.'], 422);
        }

        if (! Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Invalid password. Please try again.'], 422);
        }

        // Set deleted_by and soft delete
        $document->update([
            'deleted_by' => $user->id,
        ]);

        // Remove from Meilisearch before soft deleting
        // (Model event will also handle this, but explicit call for logging)
        $document->unsearchable();
        Log::info('Document removed from Meilisearch on soft delete', [
            'document_id' => $document->id,
        ]);

        $document->delete(); // Soft delete (sets deleted_at)

        return response()->json([
            'message' => 'Document moved to trash successfully.',
        ]);
    }

    /**
     * Approve the specified document.
     */
    public function approve(ApproveDocumentRequest $request, Document $document): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 403);
        }

        // Check if document is pending
        if ($document->status !== 'pending') {
            return response()->json(['message' => 'Only pending documents can be approved.'], 422);
        }

        // Update document status
        $document->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_message' => $request->input('review_message') ?: "File approved by {$employee->full_name}",
        ]);

        // Dispatch extraction job for newly approved PDF files only (async)
        // Word/PPT files require manual summary (OpenAI Chat API doesn't support them)
        $isPdf = $document->mime_type === self::PDF_MIME_TYPE;
        $shouldExtractContent = $isPdf && empty($document->content);

        if ($shouldExtractContent) {
            Log::info('Dispatching extraction job for newly approved PDF document', [
                'document_id' => $document->id,
                'file_name' => $document->file_name,
                'mime_type' => $document->mime_type,
            ]);

            // Set initial extraction status
            $document->update(['extraction_status' => 'pending']);

            // Dispatch job to queue
            ExtractPdfContentJob::dispatch($document->id);
        }

        $isManualSummaryFile = $this->isDescriptionOnlyMime($document->mime_type);

        if ($isManualSummaryFile) {
            if (empty($document->content)) {
                $manualContent = $this->buildManualContent($document->description, []);
                if ($manualContent) {
                    $document->update(['content' => $manualContent]);
                    $document->refresh();
                }
            }

            if (! empty($document->content)) {
                $this->generateEmbeddingAndIndex($document);
            }
        }

        // Load relationships for response
        $document->load([
            'user:id,name,email',
            'department:id,name,code',
            'tags:id,name',
            'reviewer:id,name,email',
        ]);

        return response()->json([
            'message' => 'Document approved successfully.',
            'document' => $document,
        ]);
    }

    /**
     * Reject the specified document.
     */
    public function reject(RejectDocumentRequest $request, Document $document): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 403);
        }

        // Check if document is pending
        if ($document->status !== 'pending') {
            return response()->json(['message' => 'Only pending documents can be rejected.'], 422);
        }

        // Update document status
        $document->update([
            'status' => 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_message' => $request->input('review_message') ?: "File rejected by {$employee->full_name}",
        ]);

        // Load relationships for response
        $document->load([
            'user:id,name,email',
            'department:id,name,code',
            'tags:id,name',
            'reviewer:id,name,email',
        ]);

        return response()->json([
            'message' => 'Document rejected successfully.',
            'document' => $document,
        ]);
    }

    /**
     * Request access to a document.
     */
    public function requestAccess(StoreDocumentAccessRequest $request, Document $document): JsonResponse
    {
        $user = Auth::user();

        // Create the access request
        $accessRequest = DocumentAccessRequest::create([
            'user_id' => $user->id,
            'document_id' => $document->id,
            'status' => 'pending',
            'requested_at' => now(),
            'request_message' => $request->input('request_message'),
        ]);

        // Load relationships for response
        $accessRequest->load([
            'requester:id,name,email',
            'document' => function ($query) {
                $query->select('id', 'file_name', 'mime_type', 'accessibility', 'department_id', 'user_id', 'size')
                    ->with([
                        'department:id,name,code',
                        'user:id,name,email',
                        'tags:id,name',
                    ]);
            },
        ]);

        return response()->json([
            'message' => 'Access request submitted successfully.',
            'accessRequest' => $accessRequest,
        ], 201);
    }

    /**
     * Restore a trashed document.
     */
    public function restore(Request $request, int $document): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 403);
        }

        // Only admin and department managers can restore documents
        if (! in_array($employee->role, ['admin', 'department_manager'])) {
            return response()->json(['message' => 'You do not have permission to restore documents.'], 403);
        }

        // Validate password
        $password = $request->input('password');
        if (empty($password)) {
            return response()->json(['message' => 'Password is required to confirm restore.'], 422);
        }

        if (! Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Invalid password. Please try again.'], 422);
        }

        // Find the trashed document
        $trashedDocument = Document::onlyTrashed()->find($document);

        if (! $trashedDocument) {
            return response()->json(['message' => 'Trashed document not found.'], 404);
        }

        // Department managers can only restore documents from their department
        if ($employee->role === 'department_manager' && $trashedDocument->department_id !== $employee->department_id) {
            return response()->json(['message' => 'You can only restore documents from your department.'], 403);
        }

        // Restore the document
        $trashedDocument->restore();

        // Set restored_by and restored_at
        $trashedDocument->update([
            'restored_by' => $user->id,
            'restored_at' => now(),
        ]);

        // Re-index to Meilisearch if document is approved and has content
        // (Model event will also handle this, but explicit call for logging)
        if ($trashedDocument->status === 'approved' && ! empty($trashedDocument->content)) {
            $trashedDocument->searchable();
            Log::info('Document re-indexed to Meilisearch on restore', [
                'document_id' => $trashedDocument->id,
            ]);
        } else {
            Log::info('Document not re-indexed to Meilisearch on restore (not approved or no content)', [
                'document_id' => $trashedDocument->id,
                'status' => $trashedDocument->status,
                'has_content' => ! empty($trashedDocument->content),
            ]);
        }

        // Load relationships for response
        $trashedDocument->load([
            'user:id,name,email',
            'department:id,name,code',
            'tags:id,name',
        ]);

        return response()->json([
            'message' => 'Document restored successfully.',
            'document' => $trashedDocument,
        ]);
    }

    /**
     * Permanently delete a trashed document.
     */
    public function forceDelete(Request $request, int $document): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 403);
        }

        // Only admin and department managers can permanently delete documents
        if (! in_array($employee->role, ['admin', 'department_manager'])) {
            return response()->json(['message' => 'You do not have permission to permanently delete documents.'], 403);
        }

        // Validate password
        $password = $request->input('password');
        if (empty($password)) {
            return response()->json(['message' => 'Password is required to confirm permanent deletion.'], 422);
        }

        if (! Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Invalid password. Please try again.'], 422);
        }

        // Find the trashed document
        $trashedDocument = Document::onlyTrashed()->find($document);

        if (! $trashedDocument) {
            return response()->json(['message' => 'Trashed document not found.'], 404);
        }

        // Department managers can only permanently delete documents from their department
        if ($employee->role === 'department_manager' && $trashedDocument->department_id !== $employee->department_id) {
            return response()->json(['message' => 'You can only permanently delete documents from your department.'], 403);
        }

        // Remove from Meilisearch before permanent deletion
        // (Model event will also handle this, but explicit call for logging)
        $trashedDocument->unsearchable();
        Log::info('Document removed from Meilisearch on permanent delete', [
            'document_id' => $trashedDocument->id,
        ]);

        // Delete the physical file from storage
        if ($trashedDocument->stored_name) {
            try {
                $filePath = $trashedDocument->stored_path ?? 'documents/'.$trashedDocument->stored_name;
                Storage::disk('local')->delete($filePath);
            } catch (\Exception $e) {
                // Log error but continue with database deletion
                Log::warning('Failed to delete file from storage: '.$e->getMessage());
            }
        }

        // Permanently delete the document
        $trashedDocument->forceDelete();

        return response()->json([
            'message' => 'Document permanently deleted successfully.',
        ]);
    }

    /**
     * Restore all trashed documents (bulk restore).
     */
    public function restoreAll(Request $request): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 403);
        }

        // Only admin and department managers can restore documents
        if (! in_array($employee->role, ['admin', 'department_manager'])) {
            return response()->json(['message' => 'You do not have permission to restore documents.'], 403);
        }

        // Validate password
        $password = $request->input('password');
        if (empty($password)) {
            return response()->json(['message' => 'Password is required to confirm restore all.'], 422);
        }

        if (! Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Invalid password. Please try again.'], 422);
        }

        // Get trashed documents based on role
        $query = Document::onlyTrashed();

        // Department managers can only restore documents from their department
        if ($employee->role === 'department_manager' && $employee->department_id) {
            $query->where('department_id', $employee->department_id);
        }

        $trashedDocuments = $query->get();

        if ($trashedDocuments->isEmpty()) {
            return response()->json([
                'message' => 'No trashed documents found to restore.',
                'restored_count' => 0,
            ]);
        }

        // Restore all documents in a transaction
        DB::transaction(function () use ($trashedDocuments, $user) {
            foreach ($trashedDocuments as $document) {
                $document->restore();
                $document->update([
                    'restored_by' => $user->id,
                    'restored_at' => now(),
                ]);
            }
        });

        return response()->json([
            'message' => 'All documents restored successfully.',
            'restored_count' => $trashedDocuments->count(),
        ]);
    }

    /**
     * Permanently delete all trashed documents (bulk permanent delete).
     */
    public function forceDeleteAll(Request $request): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 403);
        }

        // Only admin and department managers can permanently delete documents
        if (! in_array($employee->role, ['admin', 'department_manager'])) {
            return response()->json(['message' => 'You do not have permission to permanently delete documents.'], 403);
        }

        // Validate password
        $password = $request->input('password');
        if (empty($password)) {
            return response()->json(['message' => 'Password is required to confirm permanent deletion.'], 422);
        }

        if (! Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Invalid password. Please try again.'], 422);
        }

        // Get trashed documents based on role
        $query = Document::onlyTrashed();

        // Department managers can only permanently delete documents from their department
        if ($employee->role === 'department_manager' && $employee->department_id) {
            $query->where('department_id', $employee->department_id);
        }

        $trashedDocuments = $query->get();

        if ($trashedDocuments->isEmpty()) {
            return response()->json([
                'message' => 'No trashed documents found to delete.',
                'deleted_count' => 0,
            ]);
        }

        // Delete all documents in a transaction
        DB::transaction(function () use ($trashedDocuments) {
            foreach ($trashedDocuments as $document) {
                // Delete the physical file from storage
                if ($document->stored_name) {
                    try {
$filePath = $document->stored_path ?? 'documents/'.$document->stored_name;
                    Storage::disk('local')->delete($filePath);
                    } catch (\Exception $e) {
                        // Log error but continue with database deletion
                        Log::warning('Failed to delete file from storage: '.$e->getMessage());
                    }
                }

                // Permanently delete the document
                $document->forceDelete();
            }
        });

        return response()->json([
            'message' => 'All documents permanently deleted successfully.',
            'deleted_count' => $trashedDocuments->count(),
        ]);
    }

    /**
     * Preview a document (PDF only).
     */
    public function preview(Document $document)
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            abort(403, 'Employee record not found.');
        }

        // Check if file is PDF
        if ($document->mime_type !== 'application/pdf') {
            abort(400, 'Preview is only available for PDF files.');
        }

        // Admin can always preview
        if ($employee->role === 'admin') {
            $filePath = $document->getStoragePath();
            if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
                abort(404, 'File not found.');
            }

            $fullPath = Storage::disk('local')->path($filePath);

            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.addslashes($document->file_name).'"',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        // Check access permissions for non-admin
        $hasAccess = false;

        // Owner always has access regardless of accessibility
        if ($document->user_id === $user->id) {
            $hasAccess = true;
        }

        // Public files
        if ($document->accessibility === 'public') {
            $hasAccess = true;
        }

        // Department files - check if user is in same department
        if ($document->accessibility === 'department' && $document->department_id === $employee->department_id) {
            $hasAccess = true;
        }

        // Private files - check if user has approved access request or is department manager
        if ($document->accessibility === 'private') {
            if ($employee->role === 'department_manager' && $document->department_id === $employee->department_id) {
                $hasAccess = true;
            } else {
                // Check for approved access request
                $hasApprovedRequest = DocumentAccessRequest::where('document_id', $document->id)
                    ->where('user_id', $user->id)
                    ->where('status', 'approved')
                    ->exists();
                $hasAccess = $hasApprovedRequest;
            }
        }

        if (! $hasAccess) {
            abort(403, 'You do not have permission to preview this document.');
        }

        $filePath = $document->getStoragePath();
        if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
            abort(404, 'File not found.');
        }

        $fullPath = Storage::disk('local')->path($filePath);

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($document->file_name).'"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Download a document.
     */
    public function download(Document $document)
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        if (! $employee) {
            abort(403, 'Employee record not found.');
        }

        // Admin can always download
        if ($employee->role === 'admin') {
            $filePath = $document->getStoragePath();
            if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
                abort(404, 'File not found.');
            }

            // Record download
            $document->downloads()->create([
                'user_id' => $user->id,
                'downloaded_at' => now(),
            ]);

            return response()->download(Storage::disk('local')->path($filePath), $document->file_name);
        }

        // Check access permissions for non-admin
        $hasAccess = false;

        // Owner always has access regardless of accessibility
        if ($document->user_id === $user->id) {
            $hasAccess = true;
        }

        // Public files
        if ($document->accessibility === 'public') {
            $hasAccess = true;
        }

        // Department files - check if user is in same department
        if ($document->accessibility === 'department' && $document->department_id === $employee->department_id) {
            $hasAccess = true;
        }

        // Private files - check if user has approved access request or is department manager
        if ($document->accessibility === 'private') {
            if ($employee->role === 'department_manager' && $document->department_id === $employee->department_id) {
                $hasAccess = true;
            } else {
                // Check for approved access request
                $hasApprovedRequest = DocumentAccessRequest::where('document_id', $document->id)
                    ->where('user_id', $user->id)
                    ->where('status', 'approved')
                    ->exists();
                $hasAccess = $hasApprovedRequest;
            }
        }

        if (! $hasAccess) {
            abort(403, 'You do not have permission to download this document.');
        }

        $filePath = $document->getStoragePath();
        if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
            abort(404, 'File not found.');
        }

        // Record download
        $document->downloads()->create([
            'user_id' => $user->id,
            'downloaded_at' => now(),
        ]);

        return response()->download(Storage::disk('local')->path($filePath), $document->file_name);
    }

    private function normalizeManualKeywords($keywords): array
    {
        if (! is_array($keywords)) {
            return [];
        }

        return collect($keywords)
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function buildManualContent(?string $summary, array $keywords): ?string
    {
        $summary = trim((string) $summary);
        $keywordLine = collect($keywords)
            ->map(fn ($keyword) => $this->formatKeyword($keyword))
            ->filter()
            ->implode(' ');

        $parts = array_filter([$summary, $keywordLine], fn ($part) => $part !== '');

        if (empty($parts)) {
            return null;
        }

        return implode("\n\n", $parts);
    }

    private function formatKeyword(string $keyword): string
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return '';
        }

        $startsWithQuote = str_starts_with($keyword, '"');
        $endsWithQuote = str_ends_with($keyword, '"');

        if ($startsWithQuote && $endsWithQuote) {
            return $keyword;
        }

        return '"'.$keyword.'"';
    }

    private function generateEmbeddingAndIndex(Document $document): void
    {
        if (empty($document->content)) {
            return;
        }

        try {
            $embeddingService = app(EmbeddingService::class);
            $embedding = $embeddingService->generateEmbedding($document->content);

            if ($embedding) {
                $document->update([
                    'embedding' => json_encode($embedding),
                ]);
            }

            $document->refresh();

            $document->searchable();
            Log::info('Document indexed to Meilisearch with manual content/embedding', [
                'document_id' => $document->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate embedding or index document', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function isDescriptionOnlyMime(string $mimeType): bool
    {
        return in_array($mimeType, self::DESCRIPTION_ONLY_MIME_TYPES, true);
    }

    /**
     * Get extraction status for a document.
     */
    public function extractionStatus(Document $document): JsonResponse
    {
        return response()->json([
            'document_id' => $document->id,
            'extraction_status' => $document->extraction_status,
            'has_content' => ! empty($document->content),
        ]);
    }
}
