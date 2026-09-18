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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingRatesApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;
    protected ProductVariant $variant;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $this->admin->assignRole(UserRole::Admin->value);

        $this->customer = User::factory()->create([
            'role' => UserRole::Customer,
        ]);
        $this->customer->assignRole(UserRole::Customer->value);

        $category = Category::create([
            'name' => 'Sistem Pengereman',
            'slug' => 'sistem-pengereman',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Kampas Rem Depan Daytona Pro',
            'slug' => 'kampas-rem-depan-daytona-pro',
            'brand' => 'Daytona',
            'base_price' => 75000,
            'weight_gram' => 300,
            'length_cm' => 15,
            'width_cm' => 10,
            'height_cm' => 5,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'DAY-BRK-001',
            'variant_name' => 'Standard',
            'additional_price' => 0,
            'stock' => 20,
            'min_stock_alert' => 5,
            'weight_gram' => 300,
            'length_cm' => 15,
            'width_cm' => 10,
            'height_cm' => 5,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-20260918-001',
            'user_id' => $this->customer->id,
            'total_amount' => 75000,
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Processing,
            'payment_method' => 'QRIS',
            'shipping_address' => 'Jl. Kebon Jeruk No. 12, Jakarta Barat 11530',
        ]);
    }

    public function test_can_calculate_shipping_rates_for_same_region(): void
    {
        $response = $this->postJson('/api/v1/shipping/rates', [
            'destination_postal_code' => '12190',
            'items' => [
                [
                    'variant_id' => $this->variant->id,
                    'quantity' => 2,
                ],
            ],
        ]);

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
        $couriers = collect($rates)->pluck('courier_code')->unique()->values()->all();

        $this->assertContains('jne', $couriers);
        $this->assertContains('jnt', $couriers);
        $this->assertContains('sicepat', $couriers);
        $this->assertContains('gosend', $couriers); // Same area has GoSend instant
    }

    public function test_can_filter_shipping_rates_by_courier(): void
    {
        $response = $this->postJson('/api/v1/shipping/rates', [
            'destination_postal_code' => '40123',
            'courier' => 'jne',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $rates = $response->json('data.rates');
        foreach ($rates as $rate) {
            $this->assertEquals('jne', $rate['courier_code']);
        }
    }

    public function test_admin_can_generate_waybill_for_order(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/shipping/create-awb', [
            'order_id' => $this->order->id,
            'courier_code' => 'jne',
            'courier_service' => 'REG',
            'destination_postal_code' => '11530',
            'destination_address' => 'Jl. Kebon Jeruk No. 12, Jakarta Barat 11530',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.courier_code', 'jne')
            ->assertJsonPath('data.courier_service', 'REG')
            ->assertJsonPath('data.tracking_status', 'ready_to_ship');

        $waybill = $response->json('data.waybill_number');
        $this->assertNotEmpty($waybill);
        $this->assertStringStartsWith('MV-JNE-', $waybill);

        $this->order->refresh();
        $this->assertEquals($waybill, $this->order->tracking_number);
        $this->assertEquals(FulfillmentStatus::Shipped, $this->order->fulfillment_status);
    }

    public function test_customer_cannot_generate_waybill(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/admin/shipping/create-awb', [
            'order_id' => $this->order->id,
            'courier_code' => 'jne',
            'courier_service' => 'REG',
            'destination_postal_code' => '11530',
        ]);

        $response->assertStatus(403);
    }

    public function test_can_track_waybill_publicly(): void
    {
        $shippingOrder = ShippingOrder::create([
            'order_id' => $this->order->id,
            'courier_code' => 'sicepat',
            'courier_service' => 'BEST',
            'waybill_number' => 'MV-SICEPAT-260918-XYZ999',
            'tracking_status' => 'in_transit',
            'shipping_cost' => 18500,
            'origin_postal_code' => '12190',
            'destination_postal_code' => '11530',
            'raw_tracking_history' => [
                [
                    'status' => 'picked_up',
                    'description' => 'Paket telah diambil kurir',
                    'location' => 'Jakarta Selatan Hub',
                    'timestamp' => '2026-09-18T10:00:00Z',
                ],
                [
                    'status' => 'in_transit',
                    'description' => 'Paket dalam perjalanan menuju sorting center Jakarta Barat',
                    'location' => 'Transit Hub',
                    'timestamp' => '2026-09-18T14:30:00Z',
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/shipping/track/MV-SICEPAT-260918-XYZ999');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.waybill_number', 'MV-SICEPAT-260918-XYZ999')
            ->assertJsonPath('data.courier_code', 'sicepat')
            ->assertJsonPath('data.tracking_status', 'in_transit')
            ->assertJsonCount(2, 'data.history');
    }

    public function test_tracking_nonexistent_waybill_returns_404(): void
    {
        $response = $this->getJson('/api/v1/shipping/track/MV-NONEXISTENT-999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}
