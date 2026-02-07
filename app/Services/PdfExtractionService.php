<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class PdfExtractionService
{
    private const PDF_MIME_TYPE = 'application/pdf';

    /**
     * Extract text content from documents using OpenAI GPT-5 (latest model).
     * Supports PDF, Word (DOCX), and PowerPoint (PPTX) files directly.
     *
     * @param  Document  $document  The document to extract text from
     */
    public function extractText(Document $document): ?string
    {
        Log::info('Starting document text extraction', [
            'document_id' => $document->id,
            'file_name' => $document->file_name,
            'mime_type' => $document->mime_type,
        ]);

        try {
            // Check if file type is supported by OpenAI Files API
            if (! $this->isSupportedFileType($document->mime_type)) {
                Log::info('Document mime type not supported for extraction', [
                    'document_id' => $document->id,
                    'mime_type' => $document->mime_type,
                ]);

                return null;
            }

            // Get the file path
            $filePath = 'private/documents/'.$document->stored_name;
            $fullPath = Storage::disk('local')->path($filePath);

            if (! Storage::disk('local')->exists($filePath)) {
                Log::error('Document file not found in storage', [
                    'document_id' => $document->id,
                    'file_path' => $filePath,
                ]);

                return null;
            }

            return $this->extractTextFromFile($fullPath, $document);
        } catch (\Exception $e) {
            Log::error('Document text extraction failed', [
                'document_id' => $document->id,
                'mime_type' => $document->mime_type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Check if the mime type is supported by OpenAI Files API.
     */
    private function isSupportedFileType(string $mimeType): bool
    {
        $supportedTypes = [
            'application/pdf', // PDF
            'application/msword', // DOC
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // DOCX
            'application/vnd.ms-powerpoint', // PPT
            'application/vnd.openxmlformats-officedocument.presentationml.presentation', // PPTX
        ];

        return in_array($mimeType, $supportedTypes, true);
    }

    /**
     * Extract text from file using OpenAI Files API.
     * Supports PDF, Word (DOCX), and PowerPoint (PPTX) files.
     */
    private function extractTextFromFile(string $filePath, Document $document): ?string
    {
        try {
            // Step 1: Upload file to OpenAI Files API
            Log::info('Uploading file to OpenAI Files API', [
                'document_id' => $document->id,
                'file_path' => $filePath,
                'mime_type' => $document->mime_type,
                'file_size' => filesize($filePath),
            ]);

            $file = OpenAI::files()->upload([
                'purpose' => 'assistants',
                'file' => fopen($filePath, 'r'),
            ]);

            $fileId = $file->id;
            Log::info('File uploaded to OpenAI', [
                'document_id' => $document->id,
                'openai_file_id' => $fileId,
                'mime_type' => $document->mime_type,
            ]);

            // Step 2: Wait for file to be processed (polling)
            $maxAttempts = 30;
            $attempt = 0;
            $fileStatus = 'uploaded';

            while ($fileStatus !== 'processed' && $attempt < $maxAttempts) {
                sleep(2); // Wait 2 seconds between checks
                $attempt++;

                $fileInfo = OpenAI::files()->retrieve($fileId);
                $fileStatus = $fileInfo->status;

                Log::info('Checking file processing status', [
                    'document_id' => $document->id,
                    'openai_file_id' => $fileId,
                    'status' => $fileStatus,
                    'attempt' => $attempt,
                ]);

                if ($fileStatus === 'error') {
                    Log::error('OpenAI file processing failed', [
                        'document_id' => $document->id,
                        'openai_file_id' => $fileId,
                    ]);

                    // Clean up: delete the file from OpenAI
                    try {
                        OpenAI::files()->delete($fileId);
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete OpenAI file', [
                            'openai_file_id' => $fileId,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    return null;
                }
            }

            if ($fileStatus !== 'processed') {
                Log::error('OpenAI file processing timeout', [
                    'document_id' => $document->id,
                    'openai_file_id' => $fileId,
                    'attempts' => $attempt,
                ]);

                // Clean up: delete the file from OpenAI
                try {
                    OpenAI::files()->delete($fileId);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete OpenAI file', [
                        'openai_file_id' => $fileId,
                        'error' => $e->getMessage(),
                    ]);
                }

                return null;
            }

            Log::info('File processed, extracting text with GPT-4o (latest model)', [
                'document_id' => $document->id,
                'openai_file_id' => $fileId,
            ]);

            // Step 3: Use GPT-5 (latest model) to extract detailed summary and keywords
            $extractionPrompt = <<<'PROMPT'
You are an HR compliance assistant. Read the attached PDF and produce:

1. An exhaustive, comprehensive summary (250-700 words) written in the same tone as the document. This must be a thorough, detailed account that captures EVERY detail from the document—nothing should be omitted. Cover all sections of the document including headers, body content, tables, lists, footnotes, appendices, sidebars, captions, and any other relevant information. Extract and include: the purpose, objectives, goals, responsibilities, roles, people involved (names, titles, departments), deadlines, dates, timelines, required actions, procedures, steps, processes, policies, rules, regulations, departments, divisions, case numbers, reference numbers, locations, addresses, contact information, amounts, figures, statistics, codes, identifiers, requirements, conditions, exceptions, limitations, consequences, penalties, benefits, qualifications, criteria, standards, specifications, technical details, definitions, terms, acronyms, and any other information present in the document. Be exhaustive—include minor details, sub-points, examples, clarifications, and supplementary information. Do not summarize or condense—instead, comprehensively describe everything. Keep exact names, dates, numbers, codes, and all specific details as they appear in the document. CRITICAL: Output the summary as plain text only with absolutely NO formatting. Remove all markdown syntax (no ** for bold, no # for headers, no - or * for bullets, no numbers for lists). Remove all section headers, titles, labels, or structural markers like "Purpose:", "Scope:", "Roles and Responsibilities:". Do not use bullet points, numbered lists, or any list formatting. Write everything as a single, continuous paragraph of plain text. Do not include any formatting characters, symbols, or structural elements—only regular text with periods and commas for punctuation.

2. A keyword line containing exact phrases from the document, with emphasis on every unique person's full name. Include departments, policy names, case numbers, dates, locations, job titles, project names, and other significant identifiers. Generate at least 30 keywords for short documents (1-2 pages), 50-70 keywords for medium documents (3-5 pages), and 80-100+ keywords for longer documents (6+ pages). Each keyword must be quoted and separated by spaces.

Example: "HRNexus" "Form 123" "Jane Doe" "John Smith" "Human Resources" "2025-11-24" "Case #2025-001" "Building A"

Never refuse unless required by policy. If policy blocks direct quotes, paraphrase but still cover every critical point. Do not include any labels such as "Summary" or "Keywords"—just output the summary followed by the keyword line.
PROMPT;

            Log::info('Using extraction prompt', [
                'document_id' => $document->id,
                'prompt' => $extractionPrompt,
            ]);

            // Use GPT-5 (latest model) with correct file attachment structure
            // The 'file' parameter must be an object with 'file_id' property
            // Try gpt-5-2025-08-07 first, fallback to gpt-5, then gpt-4o
            $response = null;
            $extractedText = null;

            $createRequest = function ($modelName) use ($extractionPrompt, $fileId) {
                return OpenAI::chat()->create([
                    'model' => $modelName,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $extractionPrompt,
                                ],
                                [
                                    'type' => 'file',
                                    'file' => [
                                        'file_id' => $fileId,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'max_tokens' => 16000,
                ]);
            };

            // Try gpt-5-2025-08-07 first
            try {
                $response = $createRequest('gpt-5-2025-08-07');
                $extractedText = $response->choices[0]->message->content ?? null;
                Log::info('Successfully used gpt-5-2025-08-07 model', [
                    'document_id' => $document->id,
                ]);
            } catch (\Exception $e) {
                // If gpt-5-2025-08-07 fails, try gpt-5
                Log::warning('GPT-5-2025-08-07 model not available, trying gpt-5', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);

                try {
                    $response = $createRequest('gpt-5');
                    $extractedText = $response->choices[0]->message->content ?? null;
                    Log::info('Successfully used gpt-5 model', [
                        'document_id' => $document->id,
                    ]);
                } catch (\Exception $e2) {
                    // Final fallback to gpt-4o
                    Log::warning('GPT-5 model not available, falling back to gpt-4o', [
                        'document_id' => $document->id,
                        'error' => $e2->getMessage(),
                    ]);

                    $response = $createRequest('gpt-4o');
                    $extractedText = $response->choices[0]->message->content ?? null;
                }
            }

            if (empty($extractedText)) {
                Log::warning('No text extracted from PDF', [
                    'document_id' => $document->id,
                    'openai_file_id' => $fileId,
                ]);
            } else {
                Log::info('Text extraction successful', [
                    'document_id' => $document->id,
                    'openai_file_id' => $fileId,
                    'text_length' => strlen($extractedText),
                ]);
            }

            // Clean up: delete the file from OpenAI
            try {
                OpenAI::files()->delete($fileId);
                Log::info('OpenAI file deleted', [
                    'document_id' => $document->id,
                    'openai_file_id' => $fileId,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to delete OpenAI file', [
                    'openai_file_id' => $fileId,
                    'error' => $e->getMessage(),
                ]);
            }

            return $extractedText ? trim($extractedText) : null;
        } catch (\Exception $e) {
            Log::error('PDF text extraction failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
