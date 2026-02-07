<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class EmbeddingService
{
    /**
     * Truncate text to fit within OpenAI embedding token limit.
     * text-embedding-3-large has a limit of 8,192 tokens.
     * Rough estimate: 1 token ≈ 0.75 words, so ~6,000 words or ~30,000 characters.
     *
     * @param  int  $maxTokens  Maximum tokens (default: 8192 for text-embedding-3-large)
     */
    public function truncateForEmbedding(string $text, int $maxTokens = 8192): string
    {
        // Conservative estimate: ~30,000 characters ≈ 8,192 tokens
        // Using character count as approximation (more reliable than word count)
        $maxChars = (int) ($maxTokens * 3.66); // ~3.66 chars per token on average

        if (strlen($text) <= $maxChars) {
            return $text;
        }

        // Truncate to max characters, ensuring we don't break words
        $truncated = substr($text, 0, $maxChars);
        $lastSpace = strrpos($truncated, ' ');

        if ($lastSpace !== false && $lastSpace > $maxChars * 0.9) {
            // If we find a space near the end, truncate there to avoid breaking words
            $truncated = substr($truncated, 0, $lastSpace);
        }

        Log::info('Text truncated for embedding', [
            'original_length' => strlen($text),
            'truncated_length' => strlen($truncated),
            'max_tokens' => $maxTokens,
            'max_chars' => $maxChars,
        ]);

        return $truncated;
    }

    /**
     * Generate embedding for text using OpenAI text-embedding-3-large model.
     */
    public function generateEmbedding(string $text): ?array
    {
        if (empty(trim($text))) {
            Log::warning('Empty text provided for embedding generation');

            return null;
        }

        // Truncate text to fit within token limits
        $text = $this->truncateForEmbedding($text);

        try {
            Log::info('Generating embedding', [
                'text_length' => strlen($text),
                'model' => 'text-embedding-3-large',
            ]);

            $response = OpenAI::embeddings()->create([
                'model' => 'text-embedding-3-large',
                'input' => $text,
            ]);

            $embedding = $response->embeddings[0]->embedding ?? null;

            if (empty($embedding)) {
                Log::warning('No embedding generated', [
                    'text_length' => strlen($text),
                ]);

                return null;
            }

            Log::info('Embedding generated successfully', [
                'text_length' => strlen($text),
                'embedding_dimensions' => count($embedding),
            ]);

            return $embedding;

        } catch (\Exception $e) {
            Log::error('Embedding generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
