<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_products_and_filter_by_vehicle_compatibility(): void
    {
        $category = Category::create(['name' => 'Oli', 'slug' => 'oli']);

        $vario = Vehicle::create([
            'brand' => 'Honda',
            'model' => 'Vario 160',
            'year_start' => 2022,
        ]);

        $nmax = Vehicle::create([
            'brand' => 'Yamaha',
            'model' => 'NMAX 155',
            'year_start' => 2020,
        ]);

        // Product 1: For Vario 160 only
        $prodVario = Product::create([
            'category_id' => $category->id,
            'name' => 'Oli Honda SPX2',
            'slug' => 'oli-honda-spx2',
            'brand' => 'AHM Oil',
            'base_price' => 65000,
            'is_active' => true,
        ]);
        $prodVario->variants()->create([
            'sku' => 'AHM-SPX2-08L',
            'variant_name' => '0.8 Liter',
            'stock' => 10,
        ]);
        $prodVario->vehicles()->attach($vario->id, ['notes' => 'Cocok pas untuk matic Honda']);

        // Product 2: For NMAX only
        $prodNmax = Product::create([
            'category_id' => $category->id,
            'name' => 'Yamalube Power Matic',
            'slug' => 'yamalube-power-matic',
            'brand' => 'Yamalube',
            'base_price' => 55000,
            'is_active' => true,
        ]);
        $prodNmax->variants()->create([
            'sku' => 'YAM-PWR-1L',
            'variant_name' => '1.0 Liter',
            'stock' => 5,
        ]);
        $prodNmax->vehicles()->attach($nmax->id, ['notes' => 'Kapasitas 1L']);

        // 1. All products
        $response = $this->getJson('/api/v1/products');
        $response->assertStatus(200)->assertJsonCount(2, 'data');

        // 2. Filter by Vario
        $varioResponse = $this->getJson('/api/v1/products?vehicle_id='.$vario->id);
        $varioResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'oli-honda-spx2');

        // 3. Filter by NMAX
        $nmaxResponse = $this->getJson('/api/v1/products?vehicle_id='.$nmax->id);
        $nmaxResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'yamalube-power-matic');
    }

    public function test_can_view_product_detail_by_slug_with_variants_and_compatible_vehicles(): void
    {
        $category = Category::create(['name' => 'Busi', 'slug' => 'busi']);
        $vario = Vehicle::create(['brand' => 'Honda', 'model' => 'Vario 160', 'year_start' => 2022]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'NGK Iridium CPR9EAIX-9',
            'slug' => 'ngk-iridium-cpr9eaix-9',
            'brand' => 'NGK',
            'base_price' => 125000,
            'is_active' => true,
        ]);

        $product->variants()->create([
            'sku' => 'NGK-CPR9EAIX',
            'variant_name' => 'Standard',
            'stock' => 15,
            'min_stock_alert' => 3,
        ]);

        $product->vehicles()->attach($vario->id, ['notes' => 'Plug and play eSP+']);

        $response = $this->getJson('/api/v1/products/ngk-iridium-cpr9eaix-9');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'NGK Iridium CPR9EAIX-9')
            ->assertJsonPath('data.variants.0.sku', 'NGK-CPR9EAIX')
            ->assertJsonPath('data.compatible_vehicles.0.vehicle_id', $vario->id)
            ->assertJsonPath('data.compatible_vehicles.0.notes', 'Plug and play eSP+');
    }
}
