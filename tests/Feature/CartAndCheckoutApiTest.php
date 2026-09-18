<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartAndCheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_cart_validation_returns_availability_and_total_amount(): void
    {
        $category = Category::create(['name' => 'Oli', 'slug' => 'oli']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Motul 10W-40',
            'slug' => 'motul-10w40',
            'brand' => 'Motul',
            'base_price' => 100000,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'MOT-10W40',
            'variant_name' => '1L',
            'additional_price' => 15000,
            'stock' => 10,
        ]);

        $response = $this->postJson('/api/v1/cart/validate', [
            'items' => [
                ['sku' => 'MOT-10W40', 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.total_amount', 230000)
            ->assertJsonPath('data.items.0.sku', 'MOT-10W40');
    }

    public function test_cart_validation_fails_when_quantity_exceeds_stock(): void
    {
        $category = Category::create(['name' => 'Oli', 'slug' => 'oli']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Motul 10W-40',
            'slug' => 'motul-10w40',
            'brand' => 'Motul',
            'base_price' => 100000,
            'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'MOT-10W40',
            'variant_name' => '1L',
            'stock' => 2,
        ]);

        $response = $this->postJson('/api/v1/cart/validate', [
            'items' => [
                ['sku' => 'MOT-10W40', 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.valid', false);
    }

    public function test_customer_can_checkout_and_variant_stock_is_decremented(): void
    {
        $user = User::factory()->create(['role' => UserRole::Customer]);
        $user->assignRole(UserRole::Customer->value);
        $token = $user->createToken('test-token')->plainTextToken;

        $category = Category::create(['name' => 'Busi', 'slug' => 'busi']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Busi NGK',
            'slug' => 'busi-ngk',
            'brand' => 'NGK',
            'base_price' => 50000,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'NGK-CPR9',
            'variant_name' => 'Standard',
            'stock' => 10,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/orders/checkout', [
                'items' => [
                    ['sku' => 'NGK-CPR9', 'quantity' => 3],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', PaymentStatus::Unpaid->value)
            ->assertJsonPath('data.fulfillment_status', FulfillmentStatus::Pending->value)
            ->assertJsonPath('data.total_amount', 150000);

        // Verify stock decremented
        $this->assertEquals(7, $variant->fresh()->stock);
    }

    public function test_user_can_view_own_orders_and_order_details(): void
    {
        $user = User::factory()->create(['role' => UserRole::Customer]);
        $user->assignRole(UserRole::Customer->value);
        $token = $user->createToken('test-token')->plainTextToken;

        $category = Category::create(['name' => 'Busi', 'slug' => 'busi']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Busi NGK',
            'slug' => 'busi-ngk',
            'brand' => 'NGK',
            'base_price' => 50000,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'NGK-CPR9',
            'variant_name' => 'Standard',
            'stock' => 10,
        ]);

        $checkoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/orders/checkout', [
                'items' => [
                    ['sku' => 'NGK-CPR9', 'quantity' => 1],
                ],
            ]);

        $orderNumber = $checkoutResponse->json('data.order_number');

        // View single order
        $showResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/orders/'.$orderNumber);

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.order_number', $orderNumber);

        // View order list
        $listResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/orders');

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
