<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class PdfExtractionService
{
    /**
     * Extract text content from PDF, Word, or PowerPoint documents using OpenAI GPT-4o.
     * Excludes Excel files.
     */
    public function extractText(Document $document): ?string
    {
        Log::info('Starting document text extraction', [
            'document_id' => $document->id,
            'file_name' => $document->file_name,
            'mime_type' => $document->mime_type,
        ]);

        // Allowed mime types: PDF, Word (.docx), PowerPoint (.pptx)
        $allowedMimeTypes = [
            'application/pdf', // PDF
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // Word .docx
            'application/vnd.openxmlformats-officedocument.presentationml.presentation', // PowerPoint .pptx
        ];

        // Exclude Excel files
        $excludedMimeTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // Excel .xlsx
            'application/vnd.ms-excel', // Excel .xls
        ];

        if (in_array($document->mime_type, $excludedMimeTypes)) {
            Log::info('Document is an Excel file, skipping extraction', [
                'document_id' => $document->id,
                'mime_type' => $document->mime_type,
            ]);

            return null;
        }

        if (! in_array($document->mime_type, $allowedMimeTypes)) {
            Log::warning('Document mime type not supported for extraction', [
                'document_id' => $document->id,
                'mime_type' => $document->mime_type,
            ]);

            return null;
        }

        try {
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

            $fileType = $this->getFileTypeName($document->mime_type);

            // For Word and PowerPoint files, convert to PDF first using LibreOffice
            // OpenAI Chat API only supports PDF files for file attachments
            if ($document->mime_type !== 'application/pdf') {
                $pdfPath = $this->convertToPdf($fullPath, $document->mime_type);
                if ($pdfPath && file_exists($pdfPath)) {
                    // Extract from converted PDF
                    $extractedText = $this->extractTextFromPdfFile($pdfPath, $document);

                    // Clean up temporary PDF file
                    if (file_exists($pdfPath)) {
                        @unlink($pdfPath);
                        // Also clean up the temp directory if it's empty
                        $tempDir = dirname($pdfPath);
                        if (is_dir($tempDir) && count(scandir($tempDir)) === 2) { // Only . and ..
                            @rmdir($tempDir);
                        }
                    }

                    return $extractedText;
                } else {
                    // Fallback to direct extraction if conversion fails
                    Log::warning('PDF conversion failed, falling back to direct extraction', [
                        'document_id' => $document->id,
                    ]);

                    return $this->extractTextFromOfficeDocument($document, $fullPath, $fileType);
                }
            }

            // For PDF files, use OpenAI Files API
            return $this->extractTextFromPdfFile($fullPath, $document);
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
     * Extract text from PDF file using OpenAI Files API.
     */
    private function extractTextFromPdfFile(string $pdfPath, Document $document): ?string
    {
        try {
            // Step 1: Upload PDF to OpenAI Files API
            Log::info('Uploading PDF to OpenAI Files API', [
                'document_id' => $document->id,
                'file_size' => filesize($pdfPath),
            ]);

            $file = OpenAI::files()->upload([
                'purpose' => 'assistants',
                'file' => fopen($pdfPath, 'r'),
            ]);

            $fileId = $file->id;
            Log::info('PDF uploaded to OpenAI', [
                'document_id' => $document->id,
                'openai_file_id' => $fileId,
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

            Log::info('File processed, extracting text with GPT-4o', [
                'document_id' => $document->id,
                'openai_file_id' => $fileId,
            ]);

            // Step 3: Use GPT-4o to extract text from PDF
            // PROMPT: Extract ALL text from PDF without summarizing or condensing
            $extractionPrompt = 'Extract ALL text content from this PDF document. Include every word, sentence, and paragraph exactly as it appears. Do NOT summarize, condense, or paraphrase. Do NOT combine or merge content. Preserve the original structure and all details. Extract text from images, tables, headers, footers, and all document sections. If there are bullet points or lists, include them. If there are multiple paragraphs, include all of them. Only correct obvious OCR errors (like "rn" instead of "m") but do not change the actual words or meaning. Return the complete extracted text with all content preserved. Return ONLY the extracted text, no explanations or additional commentary.';

            Log::info('Using extraction prompt', [
                'document_id' => $document->id,
                'prompt' => $extractionPrompt,
            ]);

            // Use gpt-4o with correct file attachment structure
            // The 'file' parameter must be an object with 'file_id' property
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
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

            $extractedText = $response->choices[0]->message->content ?? null;

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

    /**
     * Extract text from Word or PowerPoint documents using PHP libraries,
     * then use GPT-4o to clean and format the text.
     */
    private function extractTextFromOfficeDocument(Document $document, string $fullPath, string $fileType): ?string
    {
        try {
            $rawText = '';

            // Extract raw text based on file type
            if ($document->mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                $rawText = $this->extractTextFromWord($fullPath);
            } elseif ($document->mime_type === 'application/vnd.openxmlformats-officedocument.presentationml.presentation') {
                $rawText = $this->extractTextFromPowerPoint($fullPath);
            }

            if (empty($rawText)) {
                Log::warning("No text extracted from {$fileType} using PHP library", [
                    'document_id' => $document->id,
                ]);

                return null;
            }

            Log::info("Raw text extracted from {$fileType}, cleaning with GPT-4o", [
                'document_id' => $document->id,
                'raw_text_length' => strlen($rawText),
            ]);

            // Use GPT-4o to clean and format the text without summarizing
            $cleaningPrompt = "The following text was extracted from a {$fileType}. Clean up formatting issues and correct obvious OCR errors (like 'rn' instead of 'm'), but DO NOT summarize, condense, or paraphrase. DO NOT combine or merge content. Preserve ALL original text, sentences, paragraphs, and structure. Include every word and detail. Only fix formatting (remove excessive whitespace, fix line breaks) and obvious OCR mistakes. Do NOT change grammar or rewrite sentences. Return ALL the cleaned text preserving the complete content. Return ONLY the cleaned text, no explanations:\n\n{$rawText}";

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $cleaningPrompt,
                    ],
                ],
                'max_tokens' => 16000,
            ]);

            $cleanedText = $response->choices[0]->message->content ?? null;

            if (empty($cleanedText)) {
                Log::warning("GPT-4o returned empty text for {$fileType}", [
                    'document_id' => $document->id,
                ]);

                return null;
            }

            Log::info("Text extraction and cleaning successful for {$fileType}", [
                'document_id' => $document->id,
                'cleaned_text_length' => strlen($cleanedText),
            ]);

            return trim($cleanedText);

        } catch (\Exception $e) {
            Log::error("Failed to extract text from {$fileType}", [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Convert Word or PowerPoint file to PDF using LibreOffice.
     * Returns path to converted PDF file, or null if conversion fails.
     */
    private function convertToPdf(string $filePath, string $mimeType): ?string
    {
        try {
            // Check if LibreOffice is available
            $libreOfficePath = $this->findLibreOffice();
            if (! $libreOfficePath) {
                Log::info('LibreOffice not found, PDF conversion unavailable', [
                    'file_path' => $filePath,
                ]);

                return null;
            }

            // Create temporary directory for conversion
            $tempDir = sys_get_temp_dir().'/pdf_conversion_'.uniqid();
            if (! mkdir($tempDir, 0755, true)) {
                Log::error('Failed to create temporary directory for PDF conversion');

                return null;
            }

            // Output PDF path
            $outputPdf = $tempDir.'/'.pathinfo($filePath, PATHINFO_FILENAME).'.pdf';

            // LibreOffice command to convert to PDF
            // Use specific PDF export filters for better results
            $pdfFilter = 'pdf'; // Generic PDF filter (works for both Word and PPT)
            // Alternative: 'pdf:writer_pdf_Export' for Word, 'pdf:impress_pdf_Export' for PPT
            // But generic 'pdf' should work fine for both

            $command = escapeshellarg($libreOfficePath).
                ' --headless'.
                ' --convert-to '.$pdfFilter.
                ' --outdir '.escapeshellarg($tempDir).
                ' '.escapeshellarg($filePath).
                ' 2>&1';

            Log::info('Converting document to PDF using LibreOffice', [
                'command' => $command,
                'file_path' => $filePath,
            ]);

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($outputPdf)) {
                Log::info('PDF conversion successful', [
                    'output_pdf' => $outputPdf,
                ]);

                return $outputPdf;
            } else {
                Log::warning('PDF conversion failed', [
                    'return_code' => $returnCode,
                    'output' => implode("\n", $output),
                ]);
                // Clean up temp directory
                $this->deleteDirectory($tempDir);

                return null;
            }
        } catch (\Exception $e) {
            Log::error('PDF conversion error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Find LibreOffice executable path.
     */
    private function findLibreOffice(): ?string
    {
        // Common LibreOffice paths on Windows
        $possiblePaths = [
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            'soffice', // If in PATH
            'soffice.exe', // If in PATH on Windows
        ];

        foreach ($possiblePaths as $path) {
            if ($path === 'soffice' || $path === 'soffice.exe') {
                // Check if command exists in PATH
                exec('where '.$path.' 2>&1', $output, $returnCode);
                if ($returnCode === 0) {
                    return $path;
                }
            } elseif (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Delete directory recursively.
     */
    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * Extract text from Word document using simple XML parsing.
     * Note: This is a basic implementation. For better results, consider using phpoffice/phpword.
     */
    private function extractTextFromWord(string $filePath): string
    {
        $text = '';

        try {
            // Word .docx files are ZIP archives containing XML files
            $zip = new \ZipArchive;
            if ($zip->open($filePath) === true) {
                // Main document content is in word/document.xml
                $documentXml = $zip->getFromName('word/document.xml');
                if ($documentXml) {
                    // Extract text from XML (simple approach - remove tags and get text)
                    $text = strip_tags($documentXml);
                    // Clean up XML entities and whitespace
                    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    $text = preg_replace('/\s+/', ' ', $text);
                    $text = trim($text);
                }
                $zip->close();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to extract text from Word document using ZIP method', [
                'error' => $e->getMessage(),
            ]);
        }

        return $text;
    }

    /**
     * Extract text from PowerPoint presentation using simple XML parsing.
     * Note: This is a basic implementation. For better results, consider using phpoffice/phppresentation.
     */
    private function extractTextFromPowerPoint(string $filePath): string
    {
        $text = '';

        try {
            // PowerPoint .pptx files are ZIP archives containing XML files
            $zip = new \ZipArchive;
            if ($zip->open($filePath) === true) {
                // Get all slide files
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    // Extract text from slide XML files (ppt/slides/slide*.xml)
                    if (preg_match('/^ppt\/slides\/slide\d+\.xml$/', $filename)) {
                        $slideXml = $zip->getFromName($filename);
                        if ($slideXml) {
                            // Extract text from XML
                            $slideText = strip_tags($slideXml);
                            $slideText = html_entity_decode($slideText, ENT_QUOTES | ENT_XML1, 'UTF-8');
                            $slideText = preg_replace('/\s+/', ' ', $slideText);
                            $slideText = trim($slideText);
                            if (! empty($slideText)) {
                                $text .= $slideText.' ';
                            }
                        }
                    }
                }
                $zip->close();
                $text = trim($text);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to extract text from PowerPoint presentation using ZIP method', [
                'error' => $e->getMessage(),
            ]);
        }

        return $text;
    }

    /**
     * Get human-readable file type name from mime type.
     */
    private function getFileTypeName(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => 'PDF',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint presentation',
            default => 'document',
        };
    }
}
