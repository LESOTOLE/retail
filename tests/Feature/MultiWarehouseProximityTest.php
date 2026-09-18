<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiWarehouseProximityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;
    protected Warehouse $jktWh;
    protected Warehouse $bdgWh;
    protected Warehouse $sbyWh;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->admin->assignRole(UserRole::Admin->value);

        $this->customer = User::factory()->create(['role' => UserRole::Customer]);
        $this->customer->assignRole(UserRole::Customer->value);

        $category = Category::create(['name' => 'CVT & Transmisi', 'slug' => 'cvt-transmisi']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Roller CVT Daytona Racing 11g',
            'slug' => 'roller-cvt-daytona-racing-11g',
            'brand' => 'Daytona',
            'base_price' => 85000,
            'weight_gram' => 150,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'DAY-RLR-11G',
            'variant_name' => '11 Gram',
            'additional_price' => 0,
            'stock' => 50,
            'min_stock_alert' => 5,
        ]);

        // Create 3 warehouses with actual coordinates
        $this->jktWh = Warehouse::create([
            'code' => 'WH-JKT',
            'name' => 'Central Warehouse Jakarta Selatan',
            'address' => 'SCBD Jakarta',
            'city' => 'Jakarta Selatan',
            'postal_code' => '12190',
            'latitude' => -6.229728,
            'longitude' => 106.807490,
            'is_active' => true,
            'is_central' => true,
        ]);

        $this->bdgWh = Warehouse::create([
            'code' => 'WH-BDG',
            'name' => 'Branch Warehouse Bandung Dago',
            'address' => 'Dago Bandung',
            'city' => 'Bandung',
            'postal_code' => '40132',
            'latitude' => -6.890432,
            'longitude' => 107.616235,
            'is_active' => true,
            'is_central' => false,
        ]);

        $this->sbyWh = Warehouse::create([
            'code' => 'WH-SBY',
            'name' => 'Branch Warehouse Surabaya Gubeng',
            'address' => 'Gubeng Surabaya',
            'city' => 'Surabaya',
            'postal_code' => '60271',
            'latitude' => -7.265757,
            'longitude' => 112.752090,
            'is_active' => true,
            'is_central' => false,
        ]);

        // Stock allocations: Jakarta = 30, Bandung = 10, Surabaya = 2
        WarehouseStock::create([
            'warehouse_id' => $this->jktWh->id,
            'product_variant_id' => $this->variant->id,
            'stock' => 30,
            'min_stock_alert' => 5,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $this->bdgWh->id,
            'product_variant_id' => $this->variant->id,
            'stock' => 10,
            'min_stock_alert' => 3,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $this->sbyWh->id,
            'product_variant_id' => $this->variant->id,
            'stock' => 2,
            'min_stock_alert' => 3,
        ]);
    }

    public function test_can_list_warehouses_sorted_by_haversine_proximity(): void
    {
        // Coordinates for Gedung Sate, Bandung (-6.902484, 107.618683)
        $response = $this->getJson('/api/v1/warehouses?latitude=-6.902484&longitude=107.618683');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $warehouses = $response->json('data');
        $this->assertCount(3, $warehouses);
        $this->assertEquals('WH-BDG', $warehouses[0]['code']); // Closest is Bandung
        $this->assertLessThan(5.0, $warehouses[0]['distance_km']); // ~1.3 km
    }

    public function test_route_nearest_warehouse_with_sufficient_stock(): void
    {
        // User in Bandung ordering 5 pcs -> Bandung has 10 pcs -> Selects Bandung
        $response = $this->postJson('/api/v1/warehouses/route-nearest', [
            'latitude' => -6.902484,
            'longitude' => 107.618683,
            'items' => [
                [
                    'variant_id' => $this->variant->id,
                    'quantity' => 5,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.matched', true)
            ->assertJsonPath('data.warehouse.code', 'WH-BDG');
    }

    public function test_route_nearest_warehouse_falls_back_to_central_when_branch_lacks_stock(): void
    {
        // User in Bandung ordering 15 pcs -> Bandung only has 10 pcs -> Central Jakarta has 30 pcs -> Fallback to Central
        $response = $this->postJson('/api/v1/warehouses/route-nearest', [
            'latitude' => -6.902484,
            'longitude' => 107.618683,
            'items' => [
                [
                    'variant_id' => $this->variant->id,
                    'quantity' => 15,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.warehouse.code', 'WH-JKT')
            ->assertJsonPath('data.warehouse.is_central', true);
    }

    public function test_admin_can_perform_complete_stock_transfer_flow(): void
    {
        $token = $this->admin->createToken('admin-tok')->plainTextToken;

        // 1. Create Stock Transfer from Jakarta (30 pcs) to Bandung (10 pcs) for 8 pcs
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/warehouses/transfers', [
                'from_warehouse_id' => $this->jktWh->id,
                'to_warehouse_id' => $this->bdgWh->id,
                'notes' => 'Restock rutin varian Daytona 11g',
                'items' => [
                    [
                        'variant_id' => $this->variant->id,
                        'quantity' => 8,
                    ],
                ],
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $transferId = $createResponse->json('data.id');

        // 2. Dispatch Transfer (status: in_transit) -> Source Jakarta stock drops from 30 to 22
        $dispatchResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson("/api/v1/admin/warehouses/transfers/{$transferId}/status", [
                'action' => 'dispatch',
            ]);

        $dispatchResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'in_transit');

        $jktStock = WarehouseStock::where('warehouse_id', $this->jktWh->id)->where('product_variant_id', $this->variant->id)->first();
        $this->assertEquals(22, $jktStock->stock);

        // 3. Complete Transfer (status: completed) -> Destination Bandung stock increases from 10 to 18
        $completeResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson("/api/v1/admin/warehouses/transfers/{$transferId}/status", [
                'action' => 'complete',
            ]);

        $completeResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $bdgStock = WarehouseStock::where('warehouse_id', $this->bdgWh->id)->where('product_variant_id', $this->variant->id)->first();
        $this->assertEquals(18, $bdgStock->stock);
    }

    public function test_cancelling_dispatched_transfer_restores_stock_to_source(): void
    {
        $token = $this->admin->createToken('admin-tok')->plainTextToken;

        // Create transfer of 5 pcs from Jakarta (30) to Surabaya (2)
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/warehouses/transfers', [
                'from_warehouse_id' => $this->jktWh->id,
                'to_warehouse_id' => $this->sbyWh->id,
                'items' => [
                    ['variant_id' => $this->variant->id, 'quantity' => 5],
                ],
            ]);

        $transferId = $createResponse->json('data.id');

        // Dispatch -> Jakarta stock becomes 25
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson("/api/v1/admin/warehouses/transfers/{$transferId}/status", ['action' => 'dispatch']);

        $jktStock = WarehouseStock::where('warehouse_id', $this->jktWh->id)->where('product_variant_id', $this->variant->id)->first();
        $this->assertEquals(25, $jktStock->stock);

        // Cancel -> Jakarta stock restored back to 30
        $cancelResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson("/api/v1/admin/warehouses/transfers/{$transferId}/status", ['action' => 'cancel']);

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertEquals(30, $jktStock->fresh()->stock);
    }

    public function test_customer_cannot_access_transfer_endpoints(): void
    {
        $token = $this->customer->createToken('cust-tok')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/warehouses/transfers');

        $response->assertStatus(403);
    }
}
