<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveDocumentRequest;
use App\Http\Requests\RejectDocumentRequest;
use App\Http\Requests\StoreDocumentAccessRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAccessRequest;
use App\Models\Employee;
use App\Models\Tag;
use App\Services\EmbeddingService;
use App\Services\PdfExtractionService;
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
    public function index(): Response
    {
        // Fetch all documents with relationships (no filtering - frontend handles permissions)
        $documents = Document::query()
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

        return Inertia::render('Documents', [
            'documents' => $documents,
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

        // Generate unique stored name
        $storedName = Str::random(40).'.'.$extension;

        // Ensure documents directory exists
        $documentsDir = 'private/documents';
        if (! Storage::disk('local')->exists($documentsDir)) {
            Storage::disk('local')->makeDirectory($documentsDir);
        }

        // Store file
        $file->storeAs($documentsDir, $storedName, 'local');

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

        // Create document
        $document = Document::create([
            'user_id' => $user->id,
            'department_id' => $departmentId,
            'file_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
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

        // Extract content and index to Meilisearch if document is approved
        // Supported: PDF, Word (.docx), PowerPoint (.pptx)
        // Excluded: Excel files
        $supportedMimeTypes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // Word .docx
            'application/vnd.openxmlformats-officedocument.presentationml.presentation', // PowerPoint .pptx
        ];

        if ($status === 'approved' && in_array($document->mime_type, $supportedMimeTypes)) {
            Log::info('Starting content extraction for approved document', [
                'document_id' => $document->id,
                'file_name' => $document->file_name,
                'mime_type' => $document->mime_type,
            ]);

            try {
                $extractionService = app(PdfExtractionService::class);
                $extractedContent = $extractionService->extractText($document);

                if ($extractedContent) {
                    // Generate embedding for the extracted content
                    Log::info('Generating embedding for extracted content', [
                        'document_id' => $document->id,
                    ]);

                    $embeddingService = app(EmbeddingService::class);
                    $embedding = $embeddingService->generateEmbedding($extractedContent);

                    // Update document with extracted content and embedding
                    $updateData = [
                        'content' => $extractedContent,
                    ];

                    if ($embedding) {
                        $updateData['embedding'] = json_encode($embedding);
                        Log::info('Embedding generated and will be saved', [
                            'document_id' => $document->id,
                            'embedding_dimensions' => count($embedding),
                        ]);
                    } else {
                        Log::warning('Embedding generation failed, saving without embedding', [
                            'document_id' => $document->id,
                        ]);
                    }

                    $document->update($updateData);

                    Log::info('Content and embedding extracted, document updated', [
                        'document_id' => $document->id,
                        'content_length' => strlen($extractedContent),
                        'has_embedding' => ! empty($embedding),
                    ]);

                    // Refresh the model to ensure we have the latest data
                    $document->refresh();

                    // Index to Meilisearch using Scout (includes embedding as _vectors)
                    try {
                        $document->searchable();
                        Log::info('Document indexed to Meilisearch with content and embedding', [
                            'document_id' => $document->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to index document to Meilisearch', [
                            'document_id' => $document->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                } else {
                    Log::warning('Content extraction returned empty, document not indexed', [
                        'document_id' => $document->id,
                    ]);
                }
            } catch (\Exception $e) {
                // Log error but don't fail the upload
                Log::error('Failed to extract content or index document', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // For Excel files (and other files without content extraction), index to Meilisearch if they have description
        $excelMimeTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            'application/vnd.ms-excel', // .xls
        ];

        if (in_array($document->mime_type, $excelMimeTypes) && $status === 'approved' && ! empty($document->description)) {
            // Excel files: index to Meilisearch with description only (no content, no embedding)
            try {
                $document->refresh(); // Ensure we have latest data
                $document->searchable();
                Log::info('Excel file indexed to Meilisearch with description only', [
                    'document_id' => $document->id,
                    'has_description' => ! empty($document->description),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to index Excel file to Meilisearch', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
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

    /**
     * Get all documents (API endpoint for refetching).
     */
    public function indexApi(): JsonResponse
    {
        $documents = Document::query()
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

        return response()->json([
            'documents' => $documents,
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
            // Search using Laravel Scout - only filter by approved status
            // Frontend will handle permission checks and show "Request Access" if needed
            $searchResults = Document::search($query)
                ->where('status', 'approved')
                ->get();

            // Load relationships for results
            $searchResults->load([
                'user:id,name,email',
                'department:id,name,code',
                'tags:id,name',
            ]);

            // Return documents in format that transformDocument expects
            // Frontend will use transformDocument to format them consistently
            $results = $searchResults->map(function ($document) {
                // Return the document as-is so transformDocument can process it
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

                // Perform vector search using hybrid search with user-provided vector
                // For user-provided embeddings, we use hybrid with semanticRatio 1.0 (pure semantic)
                $vectorSearchResults = $index->search('', [
                    'hybrid' => [
                        'embedder' => 'default',
                        'semanticRatio' => 1.0, // 100% semantic (pure vector search)
                    ],
                    'vector' => $queryEmbedding, // Pass vector as array
                    'filter' => ['status = approved'],
                    'limit' => 100,
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
            'content' => ['nullable', 'string'],
        ]);

        $document->update([
            'content' => $request->input('content'),
        ]);

        // Load relationships for response
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

        // Extract content and index to Meilisearch if document is now approved
        // Supported: PDF, Word (.docx), PowerPoint (.pptx)
        // Excluded: Excel files
        $supportedMimeTypes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // Word .docx
            'application/vnd.openxmlformats-officedocument.presentationml.presentation', // PowerPoint .pptx
        ];

        if (in_array($document->mime_type, $supportedMimeTypes) && empty($document->content)) {
            Log::info('Starting content extraction for newly approved document', [
                'document_id' => $document->id,
                'file_name' => $document->file_name,
                'mime_type' => $document->mime_type,
            ]);

            try {
                $extractionService = app(PdfExtractionService::class);
                $extractedContent = $extractionService->extractText($document);

                if ($extractedContent) {
                    // Generate embedding for the extracted content
                    Log::info('Generating embedding for extracted content', [
                        'document_id' => $document->id,
                    ]);

                    $embeddingService = app(EmbeddingService::class);
                    $embedding = $embeddingService->generateEmbedding($extractedContent);

                    // Update document with extracted content and embedding
                    $updateData = [
                        'content' => $extractedContent,
                    ];

                    if ($embedding) {
                        $updateData['embedding'] = json_encode($embedding);
                        Log::info('Embedding generated and will be saved', [
                            'document_id' => $document->id,
                            'embedding_dimensions' => count($embedding),
                        ]);
                    } else {
                        Log::warning('Embedding generation failed, saving without embedding', [
                            'document_id' => $document->id,
                        ]);
                    }

                    $document->update($updateData);

                    Log::info('Content and embedding extracted, document updated', [
                        'document_id' => $document->id,
                        'content_length' => strlen($extractedContent),
                        'has_embedding' => ! empty($embedding),
                    ]);

                    // Refresh the model to ensure we have the latest data
                    $document->refresh();

                    // Index to Meilisearch using Scout (includes embedding as _vectors)
                    try {
                        $document->searchable();
                        Log::info('Document indexed to Meilisearch with content and embedding', [
                            'document_id' => $document->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to index document to Meilisearch', [
                            'document_id' => $document->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                } else {
                    Log::warning('Content extraction returned empty, document not indexed', [
                        'document_id' => $document->id,
                    ]);
                }
            } catch (\Exception $e) {
                // Log error but don't fail the approval
                Log::error('Failed to extract content or index document during approval', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // For Excel files (and other files without content extraction), index to Meilisearch if they have description
        $excelMimeTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            'application/vnd.ms-excel', // .xls
        ];

        if (in_array($document->mime_type, $excelMimeTypes) && ! empty($document->description)) {
            // Excel files: index to Meilisearch with description only (no content, no embedding)
            try {
                $document->refresh(); // Ensure we have latest data
                $document->searchable();
                Log::info('Excel file indexed to Meilisearch with description only', [
                    'document_id' => $document->id,
                    'has_description' => ! empty($document->description),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to index Excel file to Meilisearch', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
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
                $filePath = 'private/documents/'.$trashedDocument->stored_name;
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
                        $filePath = 'private/documents/'.$document->stored_name;
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
            $filePath = 'private/documents/'.$document->stored_name;
            if (! Storage::disk('local')->exists($filePath)) {
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

        $filePath = 'private/documents/'.$document->stored_name;
        if (! Storage::disk('local')->exists($filePath)) {
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
            $filePath = 'private/documents/'.$document->stored_name;
            if (! Storage::disk('local')->exists($filePath)) {
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

        $filePath = 'private/documents/'.$document->stored_name;
        if (! Storage::disk('local')->exists($filePath)) {
            abort(404, 'File not found.');
        }

        // Record download
        $document->downloads()->create([
            'user_id' => $user->id,
            'downloaded_at' => now(),
        ]);

        return response()->download(Storage::disk('local')->path($filePath), $document->file_name);
    }
}
