<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\CheckoutService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseStockSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $cashier;
    protected Warehouse $centralWh;
    protected Warehouse $branchWh;
    protected ProductVariant $variant;
    protected CheckoutService $checkoutService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->checkoutService = app(CheckoutService::class);

        $this->customer = User::factory()->create(['role' => UserRole::Customer]);
        $this->customer->assignRole(UserRole::Customer->value);

        $this->cashier = User::factory()->create(['role' => UserRole::Staff]);
        $this->cashier->assignRole(UserRole::Staff->value);

        // Warehouses
        $this->centralWh = Warehouse::create([
            'code' => 'WH-PST',
            'name' => 'Gudang Pusat Jakarta',
            'address' => 'Jl. Sudirman No. 1',
            'city' => 'Jakarta Pusat',
            'postal_code' => '10220',
            'latitude' => -6.2115,
            'longitude' => 106.8229,
            'is_active' => true,
            'is_central' => true,
        ]);

        $this->branchWh = Warehouse::create([
            'code' => 'WH-BDG',
            'name' => 'Cabang Bandung',
            'address' => 'Jl. Dago No. 10',
            'city' => 'Bandung',
            'postal_code' => '40132',
            'latitude' => -6.8904,
            'longitude' => 107.6162,
            'is_active' => true,
            'is_central' => false,
        ]);

        // Product & Variant
        $category = Category::create(['name' => 'Pengereman', 'slug' => 'pengereman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Kampas Rem Elig Ceramic',
            'slug' => 'kampas-rem-elig-ceramic',
            'brand' => 'Elig',
            'base_price' => 75000,
            'weight_gram' => 200,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ELG-BRK-001',
            'variant_name' => 'Standard',
            'additional_price' => 0,
            'stock' => 100, // Total global stock
            'min_stock_alert' => 10,
        ]);

        // Stock in central: 70, Stock in branch: 30
        WarehouseStock::create([
            'warehouse_id' => $this->centralWh->id,
            'product_variant_id' => $this->variant->id,
            'stock' => 70,
            'min_stock_alert' => 10,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $this->branchWh->id,
            'product_variant_id' => $this->variant->id,
            'stock' => 30,
            'min_stock_alert' => 5,
        ]);
    }

    public function test_standard_checkout_decrements_global_stock_and_central_warehouse_stock(): void
    {
        $order = $this->checkoutService->checkout(
            user: $this->customer,
            items: [$this->variant->sku => 5],
            shippingData: [
                'shipping_cost' => 15000,
                'notes' => 'Testing central sync',
            ]
        );

        // Global stock decremented from 100 to 95
        $this->assertEquals(95, $this->variant->fresh()->stock);

        // Central warehouse stock decremented from 70 to 65
        $centralStock = WarehouseStock::where('warehouse_id', $this->centralWh->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(65, $centralStock->stock);

        // Branch warehouse untouched
        $branchStock = WarehouseStock::where('warehouse_id', $this->branchWh->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(30, $branchStock->stock);

        // Order recorded central warehouse id
        $this->assertEquals($this->centralWh->id, $order->warehouse_id);
    }

    public function test_pos_checkout_with_branch_warehouse_decrements_branch_stock(): void
    {
        $response = $this->actingAs($this->cashier, 'sanctum')->postJson('/api/v1/pos/orders', [
            'warehouse_id' => $this->branchWh->id,
            'items' => [
                [
                    'sku' => $this->variant->sku,
                    'quantity' => 4,
                ],
            ],
            'payment_method' => 'CASH',
            'payment_reference' => 'CASH-001',
            'notes' => 'POS Branch Sale',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.warehouse_id', $this->branchWh->id);

        // Global stock decremented from 100 to 96
        $this->assertEquals(96, $this->variant->fresh()->stock);

        // Branch warehouse decremented from 30 to 26
        $branchStock = WarehouseStock::where('warehouse_id', $this->branchWh->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(26, $branchStock->stock);

        // Central warehouse untouched
        $centralStock = WarehouseStock::where('warehouse_id', $this->centralWh->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(70, $centralStock->stock);
    }

    public function test_cancelling_order_restores_both_variant_and_warehouse_stocks(): void
    {
        $order = $this->checkoutService->checkout(
            user: $this->customer,
            items: [$this->variant->sku => 10],
            shippingData: [
                'warehouse_id' => $this->branchWh->id,
            ]
        );

        $this->assertEquals(90, $this->variant->fresh()->stock);
        $branchStock = WarehouseStock::where('warehouse_id', $this->branchWh->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(20, $branchStock->stock);

        // Cancel order
        $this->checkoutService->cancel($order);

        // Stock restored
        $this->assertEquals(100, $this->variant->fresh()->stock);
        $this->assertEquals(30, $branchStock->fresh()->stock);
    }

    public function test_expired_order_command_restores_warehouse_stock(): void
    {
        $order = $this->checkoutService->checkout(
            user: $this->customer,
            items: [$this->variant->sku => 8],
            shippingData: [
                'warehouse_id' => $this->centralWh->id,
            ]
        );

        $this->assertEquals(92, $this->variant->fresh()->stock);
        $centralStock = WarehouseStock::where('warehouse_id', $this->centralWh->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(62, $centralStock->stock);

        // Simulate 35 minutes passed
        $order->forceFill(['created_at' => now()->subMinutes(35)])->save();

        // Run cancel expired command
        $this->artisan('orders:cancel-expired', ['--ttl' => 30])
            ->expectsOutputToContain('Sukses membatalkan 1 pesanan kedaluwarsa')
            ->assertExitCode(0);

        // Assert restored
        $this->assertEquals(100, $this->variant->fresh()->stock);
        $this->assertEquals(70, $centralStock->fresh()->stock);
    }
}
