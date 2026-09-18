<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingOrder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_checkout_with_shipping_details_creates_order_and_shipping_order(): void
    {
        $user = User::factory()->create(['role' => UserRole::Customer]);
        $user->assignRole(UserRole::Customer->value);

        $category = Category::create(['name' => 'Pengereman', 'slug' => 'pengereman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Kampas Rem Brembo',
            'slug' => 'kampas-rem-brembo',
            'brand' => 'Brembo',
            'base_price' => 150000,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'BRK-BREMBO-01',
            'variant_name' => 'Front Standard',
            'additional_price' => 25000,
            'stock' => 10,
        ]);

        $payload = [
            'items' => [
                ['sku' => 'BRK-BREMBO-01', 'quantity' => 2],
            ],
            'shipping_address' => 'Jl. Sudirman No. 10, Jakarta Selatan',
            'destination_postal_code' => '12190',
            'courier_code' => 'jne',
            'courier_service' => 'REG',
            'shipping_cost' => 18000,
            'payment_method' => 'qris',
        ];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/orders/checkout', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        // Subtotal = (150000 + 25000) * 2 = 350000. Total = 350000 + 18000 = 368000
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total_amount' => 368000.00,
            'payment_method' => 'qris',
            'shipping_address' => 'Jl. Sudirman No. 10, Jakarta Selatan',
        ]);

        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order);

        $this->assertDatabaseHas('shipping_orders', [
            'order_id' => $order->id,
            'courier_code' => 'jne',
            'courier_service' => 'REG',
            'shipping_cost' => 18000.00,
            'destination_postal_code' => '12190',
        ]);
    }
}
