<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vehicle;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_storefront_receives_active_products_with_variants_and_compatible_vehicles(): void
    {
        $vehicle = Vehicle::create(['brand' => 'Honda', 'model' => 'Vario 160', 'year_start' => 2023]);
        $category = Category::create(['name' => 'Pengereman', 'slug' => 'pengereman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Brakepad Daytona Super',
            'slug' => 'brakepad-daytona-super',
            'brand' => 'Daytona',
            'base_price' => 85000,
            'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'DAY-BP-001',
            'variant_name' => 'Gold Edition',
            'additional_price' => 15000,
            'stock' => 5,
        ]);
        $product->compatibleVehicles()->attach($vehicle->id, ['notes' => 'Plug and Play']);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('products');
        $response->assertViewHas('vehicles');
        $response->assertViewHas('categories');
    }
}
