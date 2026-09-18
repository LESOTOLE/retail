<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontEndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected Vehicle $vehicle;
    protected Category $category;
    protected Product $product;
    protected ProductVariant $variant;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // 1. Create customer user
        $this->customer = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@example.com',
            'phone' => '081234567890',
            'role' => UserRole::Customer,
        ]);
        $this->customer->assignRole(UserRole::Customer->value);

        // 2. Create vehicle fixture
        $this->vehicle = Vehicle::create([
            'brand' => 'Honda',
            'model' => 'Vario 160',
            'year_start' => 2022,
            'year_end' => 2026,
        ]);

        // 3. Create warehouse fixture
        $this->warehouse = Warehouse::create([
            'code' => 'WH-JKT-01',
            'name' => 'Gudang Pusat Jakarta',
            'address' => 'Jl. Sudirman No. 1, Kebayoran Baru',
            'city' => 'Jakarta Selatan',
            'postal_code' => '12190',
            'latitude' => -6.229746,
            'longitude' => 106.807493,
            'is_active' => true,
            'is_central' => true,
        ]);

        // 4. Create category & product with variants fixture
        $this->category = Category::create([
            'name' => 'Sistem Pengereman',
            'slug' => 'sistem-pengereman',
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Kampas Rem Depan Daytona Super Pro',
            'slug' => 'kampas-rem-depan-daytona-super-pro',
            'brand' => 'Daytona',
            'base_price' => 75000,
            'weight_gram' => 300,
            'length_cm' => 15,
            'width_cm' => 10,
            'height_cm' => 5,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'DAY-BRK-VARIO160',
            'variant_name' => 'Front Standard Ceramic',
            'additional_price' => 15000,
            'stock' => 10,
            'min_stock_alert' => 3,
            'weight_gram' => 300,
            'length_cm' => 15,
            'width_cm' => 10,
            'height_cm' => 5,
        ]);

        $this->product->compatibleVehicles()->attach($this->vehicle->id, [
            'notes' => 'Direct OEM fitment untuk Vario 160 CBS & ABS',
        ]);
    }

    /**
     * Test 1: Storefront root route GET / returns status 200 and has products, categories, vehicles, warehouses, stats.
     */
    public function test_storefront_root_route_returns_ok_with_catalog_and_stats(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('welcome');
        $response->assertViewHasAll([
            'products',
            'categories',
            'vehicles',
            'warehouses',
            'stats',
        ]);

        $stats = $response->viewData('stats');
        $this->assertIsArray($stats);
        $this->assertEquals(1, $stats['vehicles_count']);
        $this->assertEquals(1, $stats['products_count']);
        $this->assertEquals(1, $stats['categories_count']);
        $this->assertEquals(1, $stats['warehouses_count']);

        $products = $response->viewData('products');
        $this->assertCount(1, $products);
        $this->assertEquals('Kampas Rem Depan Daytona Super Pro', $products->first()->name);
        $this->assertCount(1, $products->first()->compatibleVehicles);
    }

    /**
     * Test 2: POST /api/v1/cart/validate validates items and returns correct calculated totals.
     */
    public function test_cart_validate_returns_valid_true_and_calculated_totals(): void
    {
        $payload = [
            'items' => [
                [
                    'sku' => $this->variant->sku,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/cart/validate', $payload);

        // Price per unit = base_price (75000) + additional_price (15000) = 90000. For qty 2: 180000.
        $expectedSubtotal = (75000 + 15000) * 2;

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.total_amount', $expectedSubtotal)
            ->assertJsonPath('data.items.0.sku', $this->variant->sku)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price', 90000)
            ->assertJsonPath('data.items.0.subtotal', $expectedSubtotal);
    }

    /**
     * Test 3: POST /api/v1/shipping/rates returns multiple 3PL couriers with calculated rates.
     */
    public function test_shipping_rates_returns_multiple_3pl_couriers(): void
    {
        $payload = [
            'destination_postal_code' => '12190',
            'items' => [
                [
                    'variant_id' => $this->variant->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/shipping/rates', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'origin_postal_code',
                    'destination_postal_code',
                    'weight_breakdown' => [
                        'actual_weight_gram',
                        'chargeable_weight_kg',
                    ],
                    'rates' => [
                        '*' => [
                            'courier_code',
                            'courier_name',
                            'service_code',
                            'service_name',
                            'price',
                            'formatted_price',
                            'etd',
                            'is_instant',
                        ],
                    ],
                ],
            ]);

        $rates = $response->json('data.rates');
        $this->assertNotEmpty($rates);

        $couriers = collect($rates)->pluck('courier_code')->unique()->values()->all();
        $this->assertContains('jne', $couriers);
        $this->assertContains('jnt', $couriers);
        $this->assertContains('sicepat', $couriers);
        $this->assertContains('gosend', $couriers);
    }

    /**
     * Test 4: POST /api/v1/orders/checkout creates order, creates ShippingOrder, decrements stock atomically.
     */
    public function test_checkout_creates_order_and_shipping_order_and_decrements_stock(): void
    {
        $initialStock = $this->variant->stock;
        $qty = 2;
        $shippingCost = 18000;
        $unitPrice = 75000 + 15000; // 90000
        $subtotal = $unitPrice * $qty; // 180000
        $expectedTotal = $subtotal + $shippingCost; // 198000

        $payload = [
            'items' => [
                [
                    'sku' => $this->variant->sku,
                    'quantity' => $qty,
                ],
            ],
            'shipping_address' => 'Jl. Senopati No. 88, Kebayoran Baru, Jakarta Selatan',
            'destination_postal_code' => '12190',
            'courier_code' => 'jne',
            'courier_service' => 'REG',
            'shipping_cost' => $shippingCost,
            'payment_method' => 'qris',
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders/checkout', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', PaymentStatus::Unpaid->value)
            ->assertJsonPath('data.fulfillment_status', FulfillmentStatus::Pending->value)
            ->assertJsonPath('data.total_amount', $expectedTotal);

        $orderNumber = $response->json('data.order_number');
        $this->assertNotEmpty($orderNumber);

        // Verify order in database
        $this->assertDatabaseHas('orders', [
            'order_number' => $orderNumber,
            'user_id' => $this->customer->id,
            'total_amount' => $expectedTotal,
            'payment_method' => 'qris',
            'shipping_address' => 'Jl. Senopati No. 88, Kebayoran Baru, Jakarta Selatan',
        ]);

        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        // Verify ShippingOrder created and linked to order with status PENDING
        $this->assertDatabaseHas('shipping_orders', [
            'order_id' => $order->id,
            'courier_code' => 'jne',
            'courier_service' => 'REG',
            'shipping_cost' => $shippingCost,
            'destination_postal_code' => '12190',
            'tracking_status' => 'PENDING',
        ]);

        // Verify variant stock decremented atomically
        $this->assertEquals($initialStock - $qty, $this->variant->fresh()->stock);
    }

    /**
     * Test 5: Customer can view their placed order via GET /api/v1/orders and GET /api/v1/orders/{order_number}.
     */
    public function test_customer_can_view_placed_orders_and_order_details(): void
    {
        $payload = [
            'items' => [
                [
                    'sku' => $this->variant->sku,
                    'quantity' => 1,
                ],
            ],
            'shipping_address' => 'Jl. Hang Lekir No. 15, Jakarta Selatan',
            'destination_postal_code' => '12190',
            'courier_code' => 'sicepat',
            'courier_service' => 'BEST',
            'shipping_cost' => 15000,
            'payment_method' => 'bca_va',
        ];

        $checkoutResponse = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders/checkout', $payload);

        $checkoutResponse->assertStatus(201);
        $orderNumber = $checkoutResponse->json('data.order_number');

        // Customer retrieves their orders list
        $listResponse = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/orders');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $orders = $listResponse->json('data');
        $this->assertNotEmpty($orders);
        $this->assertEquals($orderNumber, $orders[0]['order_number']);

        // Customer retrieves specific order detail
        $detailResponse = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/orders/' . $orderNumber);

        $detailResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_number', $orderNumber)
            ->assertJsonPath('data.total_amount', 90000 + 15000)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.sku', $this->variant->sku);
    }

    /**
     * Test 6: GET /api/v1/shipping/track/{waybill} returns mock tracking milestone history.
     */
    public function test_public_shipping_track_returns_milestone_history(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-TRACK-TEST-001',
            'user_id' => $this->customer->id,
            'total_amount' => 105000,
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Shipped,
            'payment_method' => 'qris',
            'shipping_address' => 'Jl. Fatmawati No. 20, Jakarta Selatan',
            'tracking_number' => 'MV-JNE-260918-MOCK999',
        ]);

        $mockHistory = [
            [
                'status' => 'ready_to_ship',
                'description' => 'Paket siap diserahkan ke kurir JNE',
                'location' => 'Gudang Pusat Jakarta',
                'timestamp' => '2026-09-18T09:00:00Z',
            ],
            [
                'status' => 'picked_up',
                'description' => 'Paket telah diambil oleh kurir',
                'location' => 'Hub Jakarta Selatan',
                'timestamp' => '2026-09-18T11:30:00Z',
            ],
            [
                'status' => 'in_transit',
                'description' => 'Paket sedang transit di sorting center',
                'location' => 'Main Sorting Hub Manggarai',
                'timestamp' => '2026-09-18T14:15:00Z',
            ],
            [
                'status' => 'delivered',
                'description' => 'Paket telah berhasil diterima oleh Budi Santoso',
                'location' => 'Alamat Tujuan (Kebayoran)',
                'timestamp' => '2026-09-18T16:45:00Z',
            ],
        ];

        ShippingOrder::create([
            'order_id' => $order->id,
            'courier_code' => 'jne',
            'courier_service' => 'REG',
            'waybill_number' => 'MV-JNE-260918-MOCK999',
            'tracking_status' => 'delivered',
            'shipping_cost' => 15000,
            'origin_postal_code' => '12190',
            'destination_postal_code' => '12190',
            'destination_address' => 'Jl. Fatmawati No. 20, Jakarta Selatan',
            'raw_tracking_history' => $mockHistory,
        ]);

        $response = $this->getJson('/api/v1/shipping/track/MV-JNE-260918-MOCK999');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.waybill_number', 'MV-JNE-260918-MOCK999')
            ->assertJsonPath('data.courier_code', 'jne')
            ->assertJsonPath('data.courier_service', 'REG')
            ->assertJsonPath('data.tracking_status', 'delivered')
            ->assertJsonCount(4, 'data.history')
            ->assertJsonPath('data.history.0.status', 'ready_to_ship')
            ->assertJsonPath('data.history.3.status', 'delivered')
            ->assertJsonPath('data.history.3.location', 'Alamat Tujuan (Kebayoran)');
    }

    /**
     * Test 7: Complete End-to-End Storefront User Journey Flow.
     * Simulates a user landing on storefront, validating cart, selecting shipping, checking out,
     * viewing order history, and tracking waybill.
     */
    public function test_complete_storefront_shopping_to_delivery_tracking_flow(): void
    {
        // 1. Visit storefront homepage
        $rootResponse = $this->get('/');
        $rootResponse->assertStatus(200);
        $this->assertNotEmpty($rootResponse->viewData('products'));

        // 2. Validate Cart
        $cartResponse = $this->postJson('/api/v1/cart/validate', [
            'items' => [
                ['sku' => $this->variant->sku, 'quantity' => 1],
            ],
        ]);
        $cartResponse->assertStatus(200)->assertJsonPath('data.valid', true);

        // 3. Request Live Shipping Rates
        $ratesResponse = $this->postJson('/api/v1/shipping/rates', [
            'destination_postal_code' => '12190',
            'items' => [
                ['variant_id' => $this->variant->id, 'quantity' => 1],
            ],
        ]);
        $ratesResponse->assertStatus(200);
        $rates = $ratesResponse->json('data.rates');
        $this->assertNotEmpty($rates);
        $chosenRate = $rates[0];

        // 4. Checkout Order
        $stockBefore = $this->variant->fresh()->stock;
        $checkoutResponse = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders/checkout', [
            'items' => [
                ['sku' => $this->variant->sku, 'quantity' => 1],
            ],
            'shipping_address' => 'Jl. Gatot Subroto No. 42, Jakarta Selatan',
            'destination_postal_code' => '12190',
            'courier_code' => $chosenRate['courier_code'],
            'courier_service' => $chosenRate['service_code'],
            'shipping_cost' => $chosenRate['price'],
            'payment_method' => 'qris',
        ]);

        $checkoutResponse->assertStatus(201);
        $orderNumber = $checkoutResponse->json('data.order_number');
        $this->assertEquals($stockBefore - 1, $this->variant->fresh()->stock);

        // 5. Customer verifies order in their account
        $orderHistoryResponse = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/orders');
        $orderHistoryResponse->assertStatus(200);
        $this->assertContains($orderNumber, collect($orderHistoryResponse->json('data'))->pluck('order_number')->all());

        // 6. Simulate logistics assigning waybill and customer tracking it
        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $waybillNumber = 'MV-' . strtoupper($chosenRate['courier_code']) . '-E2E-12345';

        $order->shippingOrder()->update([
            'waybill_number' => $waybillNumber,
            'tracking_status' => 'in_transit',
            'raw_tracking_history' => [
                [
                    'status' => 'picked_up',
                    'description' => 'Paket berhasil diambil kurir',
                    'location' => 'Jakarta Selatan Warehouse',
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ]);
        $order->update(['tracking_number' => $waybillNumber]);

        $trackResponse = $this->getJson('/api/v1/shipping/track/' . $waybillNumber);
        $trackResponse->assertStatus(200)
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.waybill_number', $waybillNumber)
            ->assertJsonPath('data.tracking_status', 'in_transit')
            ->assertJsonCount(1, 'data.history');
    }
}
