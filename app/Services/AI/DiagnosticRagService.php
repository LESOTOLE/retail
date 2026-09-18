<?php

namespace App\Services\AI;

use App\Models\DiagnosticSymptom;
use App\Models\Product;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * Service RAG Diagnosa Gejala & Kerusakan Otomotif (PRD Phase 2 - Feature 2).
 */
class DiagnosticRagService
{
    public function __construct(
        protected GeminiEmbeddingService $embeddings,
        protected ProductToolExecutor $tools
    ) {}

    /**
     * Mendiagnosa keluhan pengguna dan mencocokkan suku cadang yang relevan.
     *
     * @return array<string, mixed>
     */
    public function diagnose(string $complaint, ?int $vehicleId = null): array
    {
        $symptoms = DiagnosticSymptom::all();

        if ($symptoms->isEmpty()) {
            return [
                'complaint' => $complaint,
                'vehicle_id' => $vehicleId,
                'matched_symptom' => null,
                'suspected_root_cause' => 'Belum ada basis data diagnosa gejala yang terdaftar.',
                'recommended_products' => [],
            ];
        }

        $queryVector = $this->embeddings->generateEmbedding($complaint);
        $scored = [];

        foreach ($symptoms as $symptom) {
            $symptomVector = $symptom->embedding
                ?: $this->embeddings->generateEmbedding($symptom->symptom_title.' '.$symptom->suspected_root_cause);

            $cosineScore = $this->embeddings->cosineSimilarity($queryVector, $symptomVector);

            // Bonus score jika ada keyword matching langsung
            $keywordScore = 0.0;
            if (is_array($symptom->symptom_keywords)) {
                $matchedKeywords = 0;
                foreach ($symptom->symptom_keywords as $kw) {
                    if (stripos($complaint, (string) $kw) !== false) {
                        $matchedKeywords++;
                    }
                }
                $keywordScore = min(0.4, $matchedKeywords * 0.15);
            }

            $totalScore = min(1.0, ($cosineScore * 0.7) + $keywordScore);

            $scored[] = [
                'symptom' => $symptom,
                'score' => $totalScore,
            ];
        }

        // Sort descending by score
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $bestMatch = $scored[0] ?? null;

        if (! $bestMatch || $bestMatch['score'] < 0.1) {
            return [
                'complaint' => $complaint,
                'vehicle_id' => $vehicleId,
                'matched_symptom' => null,
                'suspected_root_cause' => 'Gejala tidak spesifik. Disarankan melakukan pemeriksaan fisik komponen di bengkel resmi terdekat.',
                'recommended_products' => [],
            ];
        }

        /** @var DiagnosticSymptom $topSymptom */
        $topSymptom = $bestMatch['symptom'];
        $vehicle = $vehicleId ? Vehicle::find($vehicleId) : null;

        // Cari suku cadang yang cocok di database
        $products = [];
        $partTypes = $topSymptom->recommended_part_types ?? [];

        foreach ($partTypes as $partType) {
            $res = $this->tools->searchProducts(
                query: (string) $partType,
                vehicleId: $vehicleId,
                limit: 3
            );
            if (! empty($res['products'])) {
                $products = array_merge($products, $res['products']);
            }
        }

        // Deduplikasi SKU
        $uniqueProducts = [];
        foreach ($products as $p) {
            $sku = $p['sku'] ?? null;
            if ($sku && ! isset($uniqueProducts[$sku])) {
                $uniqueProducts[$sku] = $p;
            }
        }

        $targetVehicle = $vehicle ? " pada {$vehicle->full_name}" : '';

        return [
            'complaint' => $complaint,
            'vehicle_context' => $vehicle?->full_name,
            'matched_symptom' => [
                'title' => $topSymptom->symptom_title,
                'severity' => $topSymptom->severity,
                'confidence_score' => round($bestMatch['score'], 2),
            ],
            'suspected_root_cause' => $topSymptom->suspected_root_cause,
            'recommended_part_types' => $partTypes,
            'recommended_products' => array_values(array_slice($uniqueProducts, 0, 4)),
            'guidance' => "Berdasarkan gejala '{$complaint}'{$targetVehicle}, penyebab utama yang dicurigai adalah: {$topSymptom->suspected_root_cause}.",
        ];
    }
}
