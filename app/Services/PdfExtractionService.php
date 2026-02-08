<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class PdfExtractionService
{
    private const PDF_MIME_TYPE = 'application/pdf';

    /** Print to console when running queue:work so you can see what's happening. */
    private function console(string $message, array $context = []): void
    {
        $line = '[Extraction] '.$message;
        if ($context !== []) {
            $line .= ' '.json_encode($context);
        }
        error_log($line);
    }

    /**
     * Extract text content from documents using OpenAI GPT-5 (latest model).
     * Supports PDF, Word (DOCX), and PowerPoint (PPTX) files directly.
     *
     * @param  Document  $document  The document to extract text from
     */
    public function extractText(Document $document): ?string
    {
        $logCtx = ['document_id' => $document->id, 'file_name' => $document->file_name, 'mime_type' => $document->mime_type];

        Log::info('[Extraction] step: start', $logCtx);
        $this->console('START', ['doc_id' => $document->id, 'file' => $document->file_name]);

        try {
            if (! $this->isSupportedFileType($document->mime_type)) {
                Log::warning('[Extraction] result: skipped (mime_type_not_supported)', array_merge($logCtx, ['mime_type' => $document->mime_type]));
                $this->console('SKIP mime_type_not_supported', ['mime_type' => $document->mime_type]);

                return null;
            }

            // Use stored_path from DB (relative to disk root). Fallback for older records.
            $filePath = $document->stored_path ?? 'documents/'.$document->stored_name;
            if (! Storage::disk('local')->exists($filePath) && $document->stored_name) {
                $filePath = 'documents/'.$document->stored_name;
            }
            $fullPath = Storage::disk('local')->path($filePath);
            $diskRoot = Storage::disk('local')->path('');

            Log::info('[Extraction] step: path_resolved', array_merge($logCtx, [
                'relative_path' => $filePath,
                'full_path' => $fullPath,
                'disk_root' => $diskRoot,
            ]));

            if (! Storage::disk('local')->exists($filePath)) {
                Log::error('[Extraction] result: failed (file_not_found)', array_merge($logCtx, [
                    'relative_path' => $filePath,
                    'full_path' => $fullPath,
                    'disk_root' => $diskRoot,
                ]));
                $this->console('FAIL file_not_found', ['path' => $filePath, 'full' => $fullPath]);

                return null;
            }

            if (! is_readable($fullPath)) {
                Log::error('[Extraction] result: failed (file_not_readable)', array_merge($logCtx, [
                    'path' => $fullPath,
                    'hint' => 'Check permissions and queue worker user',
                ]));
                $this->console('FAIL file_not_readable', ['path' => $fullPath]);

                return null;
            }

            Log::info('[Extraction] step: calling_extractTextFromFile', $logCtx);
            $this->console('file found, calling OpenAI...', ['path' => $filePath]);

            return $this->extractTextFromFile($fullPath, $document);
        } catch (\Throwable $e) {
            Log::error('[Extraction] result: failed (exception_in_extractText)', array_merge($logCtx, [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]));
            $this->console('EXCEPTION', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

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
        $logCtx = ['document_id' => $document->id, 'file_path' => $filePath];
        $fileId = null;

        try {
            Log::info('[Extraction] step: open_local_file', $logCtx);

            $handle = @fopen($filePath, 'r');
            if ($handle === false) {
                Log::error('[Extraction] result: failed (fopen_failed)', array_merge($logCtx, [
                    'path' => $filePath,
                    'hint' => 'Permission denied or path wrong?',
                ]));
                $this->console('FAIL fopen_failed', ['path' => $filePath]);

                return null;
            }

            Log::info('[Extraction] step: upload_to_openai', array_merge($logCtx, [
                'file_size' => filesize($filePath),
                'mime_type' => $document->mime_type,
            ]));

            try {
                $file = OpenAI::files()->upload([
                    'purpose' => 'assistants',
                    'file' => $handle,
                ]);
                $fileId = $file->id;
            } catch (\Throwable $e) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                Log::error('[Extraction] result: failed (openai_upload_failed)', array_merge($logCtx, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]));
                $this->console('FAIL openai_upload_failed', ['error' => $e->getMessage()]);

                return null;
            }
            if (is_resource($handle)) {
                fclose($handle);
            }

            Log::info('[Extraction] step: openai_upload_ok', array_merge($logCtx, ['openai_file_id' => $fileId]));
            $this->console('upload_ok', ['openai_file_id' => $fileId]);

            // Step 2: Wait for file to be processed (polling)
            Log::info('[Extraction] step: poll_openai_status', array_merge($logCtx, ['openai_file_id' => $fileId]));

            $maxAttempts = 30;
            $attempt = 0;
            $fileStatus = 'uploaded';

            while ($fileStatus !== 'processed' && $attempt < $maxAttempts) {
                sleep(2);
                $attempt++;

                try {
                    $fileInfo = OpenAI::files()->retrieve($fileId);
                    $fileStatus = $fileInfo->status;
                } catch (\Throwable $e) {
                    Log::error('[Extraction] result: failed (openai_retrieve_failed)', array_merge($logCtx, [
                        'openai_file_id' => $fileId,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]));
                    $this->deleteOpenAiFile($fileId, $logCtx);

                    return null;
                }

                Log::info('[Extraction] step: openai_status', array_merge($logCtx, [
                    'openai_file_id' => $fileId,
                    'status' => $fileStatus,
                    'attempt' => $attempt,
                ]));

                if ($fileStatus === 'error') {
                    Log::error('[Extraction] result: failed (openai_file_status_error)', array_merge($logCtx, ['openai_file_id' => $fileId]));
                    $this->console('FAIL openai_file_status_error', ['openai_file_id' => $fileId]);
                    $this->deleteOpenAiFile($fileId, $logCtx);

                    return null;
                }
            }

            if ($fileStatus !== 'processed') {
                Log::error('[Extraction] result: failed (openai_poll_timeout)', array_merge($logCtx, [
                    'openai_file_id' => $fileId,
                    'attempts' => $attempt,
                ]));
                $this->console('FAIL openai_poll_timeout', ['attempts' => $attempt]);
                $this->deleteOpenAiFile($fileId, $logCtx);

                return null;
            }

            Log::info('[Extraction] step: openai_processed_ok', array_merge($logCtx, ['openai_file_id' => $fileId]));
            $this->console('file processed, calling chat...', ['openai_file_id' => $fileId]);

            // Step 3: Use GPT-5 (latest model) to extract detailed summary and keywords
            $extractionPrompt = <<<'PROMPT'
You are an HR compliance assistant. Read the attached PDF and produce:

1. An exhaustive, comprehensive summary (250-700 words) written in the same tone as the document. This must be a thorough, detailed account that captures EVERY detail from the document—nothing should be omitted. Cover all sections of the document including headers, body content, tables, lists, footnotes, appendices, sidebars, captions, and any other relevant information. Extract and include: the purpose, objectives, goals, responsibilities, roles, people involved (names, titles, departments), deadlines, dates, timelines, required actions, procedures, steps, processes, policies, rules, regulations, departments, divisions, case numbers, reference numbers, locations, addresses, contact information, amounts, figures, statistics, codes, identifiers, requirements, conditions, exceptions, limitations, consequences, penalties, benefits, qualifications, criteria, standards, specifications, technical details, definitions, terms, acronyms, and any other information present in the document. Be exhaustive—include minor details, sub-points, examples, clarifications, and supplementary information. Do not summarize or condense—instead, comprehensively describe everything. Keep exact names, dates, numbers, codes, and all specific details as they appear in the document. CRITICAL: Output the summary as plain text only with absolutely NO formatting. Remove all markdown syntax (no ** for bold, no # for headers, no - or * for bullets, no numbers for lists). Remove all section headers, titles, labels, or structural markers like "Purpose:", "Scope:", "Roles and Responsibilities:". Do not use bullet points, numbered lists, or any list formatting. Write everything as a single, continuous paragraph of plain text. Do not include any formatting characters, symbols, or structural elements—only regular text with periods and commas for punctuation.

2. A keyword line containing exact phrases from the document, with emphasis on every unique person's full name. Include departments, policy names, case numbers, dates, locations, job titles, project names, and other significant identifiers. Generate at least 30 keywords for short documents (1-2 pages), 50-70 keywords for medium documents (3-5 pages), and 80-100+ keywords for longer documents (6+ pages). Each keyword must be quoted and separated by spaces.

Example: "HRNexus" "Form 123" "Jane Doe" "John Smith" "Human Resources" "2025-11-24" "Case #2025-001" "Building A"

Never refuse unless required by policy. If policy blocks direct quotes, paraphrase but still cover every critical point. Do not include any labels such as "Summary" or "Keywords"—just output the summary followed by the keyword line.
PROMPT;

            Log::info('[Extraction] step: using_extraction_prompt', [
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

            // Step 3: Chat completion to extract text
            Log::info('[Extraction] step: chat_completion', array_merge($logCtx, ['openai_file_id' => $fileId]));

            $coerceContent = function ($raw) {
                if ($raw === null) {
                    return null;
                }
                return is_string($raw) ? $raw : (string) $raw;
            };

            try {
                $response = $createRequest('gpt-5-2025-08-07');
                $extractedText = $coerceContent($response->choices[0]->message->content ?? null);
                Log::info('[Extraction] step: chat_ok', array_merge($logCtx, ['model' => 'gpt-5-2025-08-07']));
            } catch (\Throwable $e) {
                Log::warning('[Extraction] step: gpt5_fallback', array_merge($logCtx, ['error' => $e->getMessage()]));
                try {
                    $response = $createRequest('gpt-5');
                    $extractedText = $coerceContent($response->choices[0]->message->content ?? null);
                    Log::info('[Extraction] step: chat_ok', array_merge($logCtx, ['model' => 'gpt-5']));
                } catch (\Throwable $e2) {
                    Log::warning('[Extraction] step: gpt4o_fallback', array_merge($logCtx, ['error' => $e2->getMessage()]));
                    try {
                        $response = $createRequest('gpt-4o');
                        $extractedText = $coerceContent($response->choices[0]->message->content ?? null);
                        Log::info('[Extraction] step: chat_ok', array_merge($logCtx, ['model' => 'gpt-4o']));
                    } catch (\Throwable $e3) {
                        Log::error('[Extraction] result: failed (chat_completion_failed)', array_merge($logCtx, [
                            'error' => $e3->getMessage(),
                            'trace' => $e3->getTraceAsString(),
                        ]));
                        $this->console('FAIL chat_completion_failed', ['error' => $e3->getMessage()]);
                        $this->deleteOpenAiFile($fileId, $logCtx);

                        return null;
                    }
                }
            }

            $hasUsableContent = is_string($extractedText) && trim($extractedText) !== '';
            $extractedLen = $hasUsableContent ? strlen($extractedText) : 0;
            Log::info('[Extraction] step: post_chat', array_merge($logCtx, [
                'openai_file_id' => $fileId,
                'extracted_length' => $extractedLen,
            ]));
            $this->console('post_chat', ['extracted_length' => $extractedLen]);

            if (! $hasUsableContent) {
                $diag = array_merge($logCtx, ['openai_file_id' => $fileId]);
                try {
                    $resp = $response ?? null;
                    if ($resp !== null && isset($resp->choices) && is_array($resp->choices) && isset($resp->choices[0])) {
                        $c0 = $resp->choices[0];
                        $diag['finish_reason'] = $c0->finishReason ?? 'n/a';
                        $msg = $c0->message ?? null;
                        if ($msg !== null && isset($msg->content)) {
                            $raw = $msg->content;
                            $diag['content_type'] = gettype($raw);
                            if (is_string($raw)) {
                                $diag['content_len'] = strlen($raw);
                                $diag['preview'] = substr($raw, 0, 150);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $diag['diag_error'] = $e->getMessage();
                }
                Log::warning('[Extraction] result: empty (empty_extracted_text)', $diag);
                $this->console('EMPTY content', ['finish_reason' => $diag['finish_reason'] ?? 'n/a', 'content_type' => $diag['content_type'] ?? 'n/a']);
            } else {
                Log::info('[Extraction] result: success (extraction_done)', array_merge($logCtx, [
                    'openai_file_id' => $fileId,
                    'text_length' => is_string($extractedText) ? strlen($extractedText) : 0,
                ]));
                $this->console('OK extraction_done', ['text_length' => is_string($extractedText) ? strlen($extractedText) : 0]);
            }

            $this->deleteOpenAiFile($fileId, $logCtx);

            return is_string($extractedText) && trim($extractedText) !== '' ? trim($extractedText) : null;
        } catch (\Throwable $e) {
            Log::error('[Extraction] result: failed (exception_in_extractTextFromFile)', array_merge($logCtx, [
                'openai_file_id' => $fileId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]));
            $this->console('EXCEPTION in extractTextFromFile', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            if ($fileId !== null) {
                $this->deleteOpenAiFile($fileId, $logCtx);
            }

            return null;
        }
    }

    private function deleteOpenAiFile(?string $fileId, array $logCtx): void
    {
        if ($fileId === null) {
            return;
        }
        try {
            OpenAI::files()->delete($fileId);
            Log::info('[Extraction] step: openai_file_deleted', array_merge($logCtx, ['openai_file_id' => $fileId]));
        } catch (\Throwable $e) {
            Log::warning('[Extraction] step: openai_file_delete_failed', array_merge($logCtx, [
                'openai_file_id' => $fileId,
                'error' => $e->getMessage(),
            ]));
        }
    }
}
