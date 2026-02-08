<?php

namespace App\Jobs;

use App\Events\PdfExtractionCompleted;
use App\Models\Document;
use App\Services\EmbeddingService;
use App\Services\PdfExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExtractPdfContentJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $documentId
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $document = Document::find($this->documentId);

        if (! $document) {
            Log::warning('[Extraction] job_result: skipped (document_not_found)', [
                'document_id' => $this->documentId,
            ]);

            return;
        }

        // Refresh document to ensure we have latest data
        $document->refresh();

        // Process PDF, Word (DOCX), and PowerPoint (PPTX) files directly
        // OpenAI Files API supports these formats natively - no conversion needed!

        // Only process approved documents
        if ($document->status !== 'approved') {
            Log::info('[Extraction] job_result: skipped (document_not_approved)', [
                'document_id' => $document->id,
                'status' => $document->status,
            ]);

            return;
        }

        try {
            // Update status to processing
            $document->update(['extraction_status' => 'processing']);

            Log::info('[Extraction] job_started', [
                'document_id' => $document->id,
                'file_name' => $document->file_name,
                'mime_type' => $document->mime_type,
            ]);

            // Extract text directly - OpenAI Files API supports PDF, Word (DOCX), and PowerPoint (PPTX) natively
            // No conversion needed!
            $extractionService = app(PdfExtractionService::class);
            $extractedContent = $extractionService->extractText($document);

            if ($extractedContent) {
                // Generate embedding
                Log::info('[Extraction] step: generating_embedding', [
                    'document_id' => $document->id,
                ]);

                $embeddingService = app(EmbeddingService::class);
                $embedding = $embeddingService->generateEmbedding($extractedContent);

                // Update document with extracted content and embedding
                $updateData = [
                    'content' => $extractedContent,
                    'extraction_status' => 'completed',
                ];

                if ($embedding) {
                    $updateData['embedding'] = json_encode($embedding);
                    Log::info('[Extraction] step: embedding_saved', [
                        'document_id' => $document->id,
                        'embedding_dimensions' => count($embedding),
                    ]);
                } else {
                    Log::warning('[Extraction] step: embedding_failed_saving_without', [
                        'document_id' => $document->id,
                    ]);
                }

                $document->update($updateData);

                Log::info('[Extraction] step: document_updated', [
                    'document_id' => $document->id,
                    'content_length' => strlen($extractedContent),
                    'has_embedding' => ! empty($embedding),
                ]);

                // Refresh the model to ensure we have the latest data
                $document->refresh();

                // Index to Meilisearch using Scout (includes embedding as _vectors)
                try {
                    $document->searchable();
                    Log::info('[Extraction] job_result: success (indexed)', [
                        'document_id' => $document->id,
                    ]);

                    // Broadcast extraction completed event (admin only)
                    PdfExtractionCompleted::dispatch($document);
                } catch (\Exception $e) {
                    Log::error('[Extraction] job_result: failed (meilisearch_index_error)', [
                        'document_id' => $document->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } else {
                // Extraction failed or returned empty
                $document->update(['extraction_status' => 'failed']);
                Log::warning('[Extraction] job_result: empty (content_extraction_returned_empty)', [
                    'document_id' => $document->id,
                    'hint' => 'Check the last [Extraction] result: log above for the exact reason (file_not_found, empty_extracted_text, etc.)',
                ]);
            }
        } catch (\Exception $e) {
            // Update status to failed
            $document->update(['extraction_status' => 'failed']);
            Log::error('[Extraction] job_result: failed (exception)', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
