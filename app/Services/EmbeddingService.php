<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class EmbeddingService
{
    /**
     * Generate embedding for text using OpenAI text-embedding-3-large model.
     *
     * @param  string  $text
     * @return array|null
     */
    public function generateEmbedding(string $text): ?array
    {
        if (empty(trim($text))) {
            Log::warning('Empty text provided for embedding generation');

            return null;
        }

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

