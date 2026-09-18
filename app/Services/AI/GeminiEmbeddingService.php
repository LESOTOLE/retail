<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service untuk vector embeddings dan kalkulasi Cosine Similarity (PRD Phase 2 - Feature 2).
 */
class GeminiEmbeddingService
{
    /**
     * Menghasilkan 768-dimensi embedding vector dari teks input.
     *
     * @return array<float>
     */
    public function generateEmbedding(string $text): array
    {
        $apiKey = config('services.gemini.api_key');

        if (filled($apiKey)) {
            try {
                $response = Http::timeout(5)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent?key={$apiKey}",
                    [
                        'model' => 'models/text-embedding-004',
                        'content' => [
                            'parts' => [
                                ['text' => $text],
                            ],
                        ],
                    ]
                );

                if ($response->successful()) {
                    $values = $response->json('embedding.values');
                    if (is_array($values) && count($values) > 0) {
                        return array_map('floatval', $values);
                    }
                }
            } catch (Throwable $e) {
                Log::warning('GeminiEmbeddingService: Panggilan API embedding gagal, beralih ke local vector generator', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->generateLocalVector($text);
    }

    /**
     * Menghitung Cosine Similarity antara dua vector (0.0 s/d 1.0).
     *
     * @param  array<float>  $vecA
     * @param  array<float>  $vecB
     */
    public function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dim = min(count($vecA), count($vecB));
        if ($dim === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $dim; $i++) {
            $a = (float) $vecA[$i];
            $b = (float) $vecB[$i];

            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        $similarity = $dotProduct / (sqrt($normA) * sqrt($normB));

        return max(0.0, min(1.0, (float) $similarity));
    }

    /**
     * Local deterministic token-frequency hash vector generator (768 dimensi).
     *
     * @return array<float>
     */
    public function generateLocalVector(string $text, int $dimensions = 768): array
    {
        $vector = array_fill(0, $dimensions, 0.0);
        $words = preg_split('/[^\p{L}\p{N}\-]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return $vector;
        }

        foreach ($words as $word) {
            // Hash word to index bucket in dimensions
            $hash = crc32($word);
            $idx = abs($hash) % $dimensions;
            $weight = min(3.0, 1.0 + (strlen($word) * 0.15));
            $vector[$idx] += $weight;
        }

        // Normalize vector
        $norm = 0.0;
        for ($i = 0; $i < $dimensions; $i++) {
            $norm += $vector[$i] * $vector[$i];
        }

        if ($norm > 0) {
            $sqrtNorm = sqrt($norm);
            for ($i = 0; $i < $dimensions; $i++) {
                $vector[$i] = round($vector[$i] / $sqrtNorm, 6);
            }
        }

        return $vector;
    }
}
