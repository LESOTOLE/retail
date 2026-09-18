<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DiagnosticSymptom;
use App\Services\AI\GeminiEmbeddingService;
use Illuminate\Database\Seeder;

/**
 * Seeder data diagnosa gejala kerusakan otomotif untuk RAG (PRD Phase 2 - Feature 2).
 */
class DiagnosticSymptomSeeder extends Seeder
{
    public function run(): void
    {
        $embeddings = app(GeminiEmbeddingService::class);

        $symptoms = [
            [
                'category_slug' => 'oli-cairan',
                'symptom_title' => 'Getaran & Gredek CVT pada Tarikan Awal',
                'symptom_description' => 'Motor matic bergetar kasar (gredek) saat mulai membuka gas dari posisi berhenti atau saat menanjak.',
                'symptom_keywords' => ['gredek', 'getar', 'tarikan awal', 'nanjak', 'cvt', 'ndut-ndutan', 'kasar'],
                'suspected_root_cause' => 'Kampas ganda slip karena kotor/aus, mangkok kopling CVT berdebu, roller CVT aus/peang, atau v-belt sudah getas/kendur.',
                'severity' => 'medium',
                'recommended_part_types' => ['Roller', 'V-Belt', 'Kampas Ganda', 'Oli Gardan'],
            ],
            [
                'category_slug' => 'busi',
                'symptom_title' => 'Mesin Brebet, Tersendat & Sulit Distarter',
                'symptom_description' => 'Mesin motor sering brebet saat digas pada RPM menengah, akselerasi loyo, atau susah hidup saat pagi hari.',
                'symptom_keywords' => ['brebet', 'tersendat', 'susah hidup', 'mati mendadak', 'loyo', 'akselerasi', 'pagi'],
                'suspected_root_cause' => 'Busi kotor atau elektroda aus sehingga percikan api kecil, filter udara kotor/tersumbat, atau sistem injeksi kotor.',
                'severity' => 'high',
                'recommended_part_types' => ['Busi', 'Filter Udara', 'Busi Iridium'],
            ],
            [
                'category_slug' => 'pengereman',
                'symptom_title' => 'Rem Berdecit & Daya Cengkeram Kurang Pakem',
                'symptom_description' => 'Timbul bunyi decitan besi tajam saat tuas rem ditarik, atau jarak pengereman terasa lebih panjang dan licin.',
                'symptom_keywords' => ['decit', 'berdecit', 'rem bunyi', 'kurang pakem', 'blong', 'pengereman', 'licin'],
                'suspected_root_cause' => 'Kampas rem (brake pad) sudah tipis/habis hingga menyentuh plat besi, piringan cakram bergelombang, atau minyak rem basi/masuk angin.',
                'severity' => 'critical',
                'recommended_part_types' => ['Kampas Rem', 'Brake Pad', 'Minyak Rem', 'Piringan Cakram'],
            ],
            [
                'category_slug' => 'aki-kelistrikan',
                'symptom_title' => 'Kelistrikan Lemah, Klakson Serak & Starter Elektrik Mati',
                'symptom_description' => 'Starter tombol tidak berputar hanya bunyi cetek-cetek, lampu speedometer redup, dan klakson bersuara pelan.',
                'symptom_keywords' => ['starter mati', 'cetek', 'aki drop', 'klakson pelan', 'lampu redup', 'kelistrikan', 'soak'],
                'suspected_root_cause' => 'Tegangan aki drop (< 12.4V) akibat usia pemakaian sel aki yang rusak atau kiprok regulator tidak mengisi normal.',
                'severity' => 'high',
                'recommended_part_types' => ['Aki', 'Aki Kering', 'Kiprok'],
            ],
            [
                'category_slug' => 'oli-cairan',
                'symptom_title' => 'Mesin Cepat Panas (Overheat) & Suara Kasar',
                'symptom_description' => 'Hawa panas terasa berlebihan di area dek kaki, indikator suhu menyala, atau suara mesin terdengar kering kasar.',
                'symptom_keywords' => ['panas', 'overheat', 'suara kasar', 'hawa panas', 'indikator suhu', 'kering'],
                'suspected_root_cause' => 'Volume oli mesin berkurang/kering, kualitas oli sudah rusak (viskositas hilang), atau air radiator coolant habis/bocor.',
                'severity' => 'critical',
                'recommended_part_types' => ['Oli Mesin', 'Coolant', 'Air Radiator', 'Oli Sintetik'],
            ],
        ];

        foreach ($symptoms as $item) {
            $category = Category::where('slug', $item['category_slug'])->first();

            $fullText = $item['symptom_title'].' '.$item['symptom_description'].' '.$item['suspected_root_cause'].' '.implode(' ', $item['symptom_keywords']);
            $vector = $embeddings->generateEmbedding($fullText);

            DiagnosticSymptom::updateOrCreate(
                ['symptom_title' => $item['symptom_title']],
                [
                    'category_id' => $category?->id,
                    'symptom_description' => $item['symptom_description'],
                    'symptom_keywords' => $item['symptom_keywords'],
                    'suspected_root_cause' => $item['suspected_root_cause'],
                    'severity' => $item['severity'],
                    'recommended_part_types' => $item['recommended_part_types'],
                    'embedding' => $vector,
                ]
            );
        }
    }
}
