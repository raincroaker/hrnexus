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
            Log::warning('Document not found for extraction job', [
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
            Log::info('Document is not approved, skipping extraction', [
                'document_id' => $document->id,
                'status' => $document->status,
            ]);

            return;
        }

        try {
            // Update status to processing
            $document->update(['extraction_status' => 'processing']);

            Log::info('Starting document extraction job', [
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
                Log::info('Generating embedding for extracted content', [
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

                    // Broadcast extraction completed event (admin only)
                    PdfExtractionCompleted::dispatch($document);
                } catch (\Exception $e) {
                    Log::error('Failed to index document to Meilisearch', [
                        'document_id' => $document->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } else {
                // Extraction failed or returned empty
                $document->update(['extraction_status' => 'failed']);
                Log::warning('Content extraction returned empty, document not indexed', [
                    'document_id' => $document->id,
                ]);
            }
        } catch (\Exception $e) {
            // Update status to failed
            $document->update(['extraction_status' => 'failed']);
            Log::error('PDF extraction job failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
