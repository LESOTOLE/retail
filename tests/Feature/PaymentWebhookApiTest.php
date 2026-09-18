<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_payment_webhook_settlement_marks_order_as_paid_and_processing(): void
    {
        $user = User::factory()->create(['role' => UserRole::Customer]);
        $user->assignRole(UserRole::Customer->value);

        $category = Category::create(['name' => 'Oli', 'slug' => 'oli']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Motul',
            'slug' => 'motul',
            'brand' => 'Motul',
            'base_price' => 100000,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'MOT-1',
            'variant_name' => '1L',
            'stock' => 10,
        ]);

        $checkoutService = app(CheckoutService::class);
        $order = $checkoutService->checkout($user, ['MOT-1' => 2]);

        $this->assertEquals(PaymentStatus::Unpaid, $order->payment_status);

        $response = $this->postJson('/api/v1/webhooks/payment', [
            'order_id' => $order->order_number,
            'transaction_status' => 'settlement',
            'transaction_id' => 'TRX-123456',
            'gross_amount' => '200000.00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.fulfillment_status', FulfillmentStatus::Processing->value);

        $this->assertEquals(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertEquals(FulfillmentStatus::Processing, $order->fresh()->fulfillment_status);
    }

    public function test_payment_webhook_is_idempotent_on_repeated_calls(): void
    {
        $user = User::factory()->create(['role' => UserRole::Customer]);
        $user->assignRole(UserRole::Customer->value);

        $category = Category::create(['name' => 'Oli', 'slug' => 'oli']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Motul',
            'slug' => 'motul',
            'brand' => 'Motul',
            'base_price' => 100000,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'MOT-1',
            'variant_name' => '1L',
            'stock' => 10,
        ]);

        $checkoutService = app(CheckoutService::class);
        $order = $checkoutService->checkout($user, ['MOT-1' => 2]);

        $payload = [
            'order_id' => $order->order_number,
            'transaction_status' => 'settlement',
            'transaction_id' => 'TRX-123456',
        ];

        // Call 1
        $res1 = $this->postJson('/api/v1/webhooks/payment', $payload);
        $res1->assertStatus(200);

        // Call 2 (identical event key)
        $res2 = $this->postJson('/api/v1/webhooks/payment', $payload);
        $res2->assertStatus(200)
            ->assertJsonPath('data.status', 'already_processed');
    }

    public function test_payment_webhook_cancel_or_expire_restores_locked_stock(): void
    {
        $user = User::factory()->create(['role' => UserRole::Customer]);
        $user->assignRole(UserRole::Customer->value);

        $category = Category::create(['name' => 'Oli', 'slug' => 'oli']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Motul',
            'slug' => 'motul',
            'brand' => 'Motul',
            'base_price' => 100000,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'MOT-1',
            'variant_name' => '1L',
            'stock' => 10,
        ]);

        $checkoutService = app(CheckoutService::class);
        $order = $checkoutService->checkout($user, ['MOT-1' => 3]);

        $this->assertEquals(7, $variant->fresh()->stock);

        $response = $this->postJson('/api/v1/webhooks/payment', [
            'order_id' => $order->order_number,
            'transaction_status' => 'expire',
            'transaction_id' => 'TRX-EXPIRE-999',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.payment_status', PaymentStatus::Expired->value);

        // Stock restored from 7 back to 10
        $this->assertEquals(10, $variant->fresh()->stock);
        $this->assertEquals(FulfillmentStatus::Cancelled, $order->fresh()->fulfillment_status);
    }
}
