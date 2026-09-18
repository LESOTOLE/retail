<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiagnosticSymptom;
use App\Models\Product;
use App\Models\Vehicle;
use App\Services\AI\DiagnosticRagService;
use App\Services\AI\GeminiEmbeddingService;
use App\Services\AI\ProductToolExecutor;
use Database\Seeders\DiagnosticSymptomSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosticRagTest extends TestCase
{
    use RefreshDatabase;

    public function test_gemini_embedding_service_calculates_cosine_similarity(): void
    {
        $service = app(GeminiEmbeddingService::class);

        $v1 = $service->generateLocalVector('motor gredek cvt getar');
        $v2 = $service->generateLocalVector('getaran cvt dan gredek');
        $v3 = $service->generateLocalVector('helm full face ukuran xl');

        $simSame = $service->cosineSimilarity($v1, $v2);
        $simDiff = $service->cosineSimilarity($v1, $v3);

        $this->assertGreaterThan(0.4, $simSame);
        $this->assertLessThan($simSame, $simDiff);
    }

    public function test_diagnostic_rag_service_matches_symptom_and_recommends_parts(): void
    {
        $vario = Vehicle::create([
            'brand' => 'Honda',
            'model' => 'Vario 160',
            'year_start' => 2022,
        ]);

        $category = Category::create(['name' => 'Oli & Cairan', 'slug' => 'oli-cairan']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Oli Gardan Gear Matic',
            'slug' => 'oli-gardan-gear-matic',
            'brand' => 'Federal',
            'base_price' => 25000,
            'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'FED-GEAR-120',
            'variant_name' => '120ml',
            'stock' => 15,
        ]);
        $product->vehicles()->attach($vario->id, ['notes' => 'Cocok untuk gardan matic']);

        $this->seed(DiagnosticSymptomSeeder::class);

        $ragService = app(DiagnosticRagService::class);
        $result = $ragService->diagnose('motor saya getar gredek di tanjakan', $vario->id);

        $this->assertNotNull($result['matched_symptom']);
        $this->assertStringContainsString('Gredek', $result['matched_symptom']['title']);
        $this->assertStringContainsString('Kampas ganda', $result['suspected_root_cause']);
        $this->assertNotEmpty($result['recommended_products']);
    }

    public function test_product_tool_executor_handles_diagnose_symptom_tool(): void
    {
        $this->seed(DiagnosticSymptomSeeder::class);

        $executor = app(ProductToolExecutor::class);
        $result = $executor->execute('diagnose_symptom', [
            'complaint' => 'rem berdecit tajam dan tidak pakem',
        ]);

        $this->assertArrayHasKey('matched_symptom', $result);
        $this->assertStringContainsString('Rem Berdecit', $result['matched_symptom']['title']);
        $this->assertStringContainsString('Kampas rem', $result['suspected_root_cause']);
    }
}
