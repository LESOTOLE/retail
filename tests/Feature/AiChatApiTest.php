<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_chat_works_with_local_fallback_when_gemini_key_is_not_set(): void
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

        $response = $this->postJson('/api/v1/ai/chat', [
            'vehicle_id' => $vario->id,
            'message' => 'Rekomendasikan oli motul untuk motor saya',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.driver', 'local-fallback')
            ->assertJsonPath('data.vehicle_context.id', $vario->id)
            ->assertJsonStructure([
                'data' => [
                    'session_token',
                    'reply',
                    'recommended_products',
                ],
            ]);

        $this->assertDatabaseHas('ai_chat_sessions', [
            'last_context_vehicle_id' => $vario->id,
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'sender' => 'user',
            'message' => 'Rekomendasikan oli motul untuk motor saya',
        ]);
    }

    public function test_ai_chat_executes_gemini_tool_calling_loop_when_configured(): void
    {
        config(['services.gemini.api_key' => 'fake-test-key']);

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
        $product->vehicles()->attach($vario->id, ['notes' => 'Cocok pas']);

        \Illuminate\Support\Facades\Http::fake([
            'https://generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'functionCall' => [
                                    'name' => 'search_products',
                                    'args' => ['query' => 'motul', 'vehicle_id' => $vario->id],
                                ],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => 'Untuk motor Anda, kami sangat menyarankan Motul Scooter Power LE 10W-40 1L.',
                            ]],
                        ],
                    ]],
                ]),
        ]);

        $response = $this->postJson('/api/v1/ai/chat', [
            'vehicle_id' => $vario->id,
            'message' => 'Cari oli motul',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.driver', 'gemini')
            ->assertJsonPath('data.reply', 'Untuk motor Anda, kami sangat menyarankan Motul Scooter Power LE 10W-40 1L.')
            ->assertJsonPath('data.recommended_products.0.sku', 'MOT-10W40');
    }
}
