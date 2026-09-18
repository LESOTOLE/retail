<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiStreamingChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_streaming_endpoint_returns_event_stream_response(): void
    {
        $vario = Vehicle::create([
            'brand' => 'Honda',
            'model' => 'Vario 160',
            'year_start' => 2022,
        ]);

        $category = Category::create(['name' => 'Oli', 'slug' => 'oli']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Motul Scooter Power LE 10W-40',
            'slug' => 'motul-scooter-power-le-10w40',
            'brand' => 'Motul',
            'base_price' => 115000,
            'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'MOT-10W40',
            'variant_name' => '1L',
            'stock' => 10,
        ]);
        $product->vehicles()->attach($vario->id, ['notes' => 'Cocok pas untuk Vario 160']);

        $response = $this->post('/api/v1/ai/chat/stream', [
            'vehicle_id' => $vario->id,
            'message' => 'Rekomendasikan oli untuk Vario 160',
        ]);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

        // Verify output contains SSE event chunks
        $content = $response->streamedContent();
        $this->assertStringContainsString('event: session', $content);
        $this->assertStringContainsString('event: token', $content);
        $this->assertStringContainsString('event: product_recommendations', $content);
        $this->assertStringContainsString('event: done', $content);
    }
}
