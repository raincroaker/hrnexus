<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DocumentConversionService
{
    /**
     * MIME types that can be converted to PDF.
     */
    private const CONVERTIBLE_MIME_TYPES = [
        'application/msword', // .doc
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/vnd.ms-powerpoint', // .ppt
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
    ];

    /**
     * Check if a MIME type can be converted to PDF.
     */
    public function canConvert(string $mimeType): bool
    {
        return in_array($mimeType, self::CONVERTIBLE_MIME_TYPES, true);
    }

    /**
     * Convert Word or PowerPoint file to PDF.
     *
     * @param  string  $sourcePath  Full path to the source file
     * @param  string  $mimeType  MIME type of the source file
     * @return string|null Path to the converted PDF file, or null on failure
     */
    public function convertToPdf(string $sourcePath, string $mimeType): ?string
    {
        if (! $this->canConvert($mimeType)) {
            Log::warning('MIME type not supported for conversion', [
                'mime_type' => $mimeType,
            ]);

            return null;
        }

        // Get LibreOffice executable path
        $sofficePath = $this->getSofficePath();
        if (! $sofficePath) {
            Log::error('LibreOffice not found on system');

            return null;
        }

        // Create temp directory for conversion output
        $tempDir = storage_path('app/temp/conversions');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Generate unique filename for converted PDF
        $pdfFileName = pathinfo($sourcePath, PATHINFO_FILENAME).'_'.uniqid().'.pdf';
        $outputPath = $tempDir.DIRECTORY_SEPARATOR.$pdfFileName;

        try {
            Log::info('Starting document conversion to PDF', [
                'source_path' => $sourcePath,
                'mime_type' => $mimeType,
                'output_path' => $outputPath,
            ]);

            // Build LibreOffice command
            // --headless: Run without GUI
            // --convert-to pdf: Convert to PDF format
            // --outdir: Output directory
            // Source file path
            $command = [
                $sofficePath,
                '--headless',
                '--convert-to',
                'pdf',
                '--outdir',
                $tempDir,
                $sourcePath,
            ];

            $process = new Process($command);
            $process->setTimeout(120); // 2 minutes timeout
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Check if PDF was created
            if (! file_exists($outputPath)) {
                Log::error('PDF conversion completed but output file not found', [
                    'expected_path' => $outputPath,
                    'command_output' => $process->getOutput(),
                ]);

                return null;
            }

            Log::info('Document successfully converted to PDF', [
                'source_path' => $sourcePath,
                'output_path' => $outputPath,
                'file_size' => filesize($outputPath),
            ]);

            return $outputPath;
        } catch (\Exception $e) {
            Log::error('Document conversion failed', [
                'source_path' => $sourcePath,
                'mime_type' => $mimeType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Clean up if PDF was partially created
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }

            return null;
        }
    }

    /**
     * Get the path to LibreOffice soffice executable.
     */
    private function getSofficePath(): ?string
    {
        // Check common installation paths
        $possiblePaths = [
            // Windows
            'C:\Program Files\LibreOffice\program\soffice.exe',
            'C:\Program Files (x86)\LibreOffice\program\soffice.exe',
            // Linux/Unix
            '/usr/bin/soffice',
            '/usr/local/bin/soffice',
            // macOS
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // Try to find soffice in PATH
        $process = new Process(['which', 'soffice']);
        $process->run();
        if ($process->isSuccessful()) {
            $path = trim($process->getOutput());
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // Try Windows where command
        if (PHP_OS_FAMILY === 'Windows') {
            $process = new Process(['where', 'soffice']);
            $process->run();
            if ($process->isSuccessful()) {
                $path = trim($process->getOutput());
                if (file_exists($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Clean up temporary PDF file.
     */
    public function cleanupTempPdf(string $pdfPath): void
    {
        if (file_exists($pdfPath) && str_contains($pdfPath, 'temp/conversions')) {
            try {
                unlink($pdfPath);
                Log::info('Temporary PDF cleaned up', [
                    'path' => $pdfPath,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to cleanup temporary PDF', [
                    'path' => $pdfPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
