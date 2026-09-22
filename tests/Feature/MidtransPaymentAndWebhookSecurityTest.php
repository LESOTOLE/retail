<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Payment\MidtransService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransPaymentAndWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_checkout_generates_and_returns_payment_payload(): void
    {
        $user = User::factory()->create(['role' => UserRole::Customer]);
        $user->assignRole(UserRole::Customer->value);
        $token = $user->createToken('test-token')->plainTextToken;

        $category = Category::create(['name' => 'Oli', 'slug' => 'oli']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Motul 7100',
            'slug' => 'motul-7100',
            'brand' => 'Motul',
            'base_price' => 150000,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'MOT-7100-1L',
            'variant_name' => '1L',
            'stock' => 10,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/orders/checkout', [
                'items' => [
                    ['sku' => 'MOT-7100-1L', 'quantity' => 1],
                ],
                'shipping_address' => 'Jl. MH Thamrin No. 1, Jakarta Pusat',
                'destination_postal_code' => '10310',
                'courier_code' => 'JNE',
                'courier_service' => 'REG',
                'shipping_cost' => 15000,
                'payment_method' => 'midtrans_snap',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_amount', 165000)
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonStructure([
                'data' => [
                    'order_number',
                    'total_amount',
                    'payment_payload' => [
                        'snap_token',
                        'redirect_url',
                        'client_key',
                    ],
                ],
            ]);
    }

    public function test_webhook_with_valid_sha512_signature_marks_order_as_paid(): void
    {
        $serverKey = 'test-midtrans-secret-key-12345';
        config(['services.midtrans.server_key' => $serverKey]);

        $user = User::factory()->create(['role' => UserRole::Customer]);
        $user->assignRole(UserRole::Customer->value);

        $category = Category::create(['name' => 'Busi', 'slug' => 'busi']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Busi Iridium',
            'slug' => 'busi-iridium',
            'brand' => 'NGK',
            'base_price' => 125000,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'NGK-IRI-1',
            'variant_name' => 'Iridium',
            'stock' => 5,
        ]);

        $order = app(CheckoutService::class)->checkout($user, ['NGK-IRI-1' => 1]);

        $orderId = $order->order_number;
        $statusCode = '200';
        $grossAmount = '125000.00';
        $validSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        $response = $this->postJson('/api/v1/webhooks/payment', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $validSignature,
            'transaction_status' => 'settlement',
            'transaction_id' => 'MIDTRANS-TRX-777',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.fulfillment_status', FulfillmentStatus::Processing->value);

        $this->assertEquals(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_webhook_with_invalid_or_tampered_signature_is_rejected(): void
    {
        $serverKey = 'test-midtrans-secret-key-12345';
        config(['services.midtrans.server_key' => $serverKey]);

        $user = User::factory()->create(['role' => UserRole::Customer]);
        $user->assignRole(UserRole::Customer->value);

        $category = Category::create(['name' => 'Busi', 'slug' => 'busi']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Busi Iridium',
            'slug' => 'busi-iridium-2',
            'brand' => 'NGK',
            'base_price' => 125000,
            'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'NGK-IRI-2',
            'variant_name' => 'Iridium',
            'stock' => 5,
        ]);

        $order = app(CheckoutService::class)->checkout($user, ['NGK-IRI-2' => 1]);

        $fakeSignature = 'fake-tampered-signature-sha512-value';

        $response = $this->postJson('/api/v1/webhooks/payment', [
            'order_id' => $order->order_number,
            'status_code' => '200',
            'gross_amount' => '125000.00',
            'signature_key' => $fakeSignature,
            'transaction_status' => 'settlement',
            'transaction_id' => 'MIDTRANS-TRX-HACK',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'INVALID_WEBHOOK_SIGNATURE');

        // Order remains unpaid
        $this->assertEquals(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    public function test_xendit_webhook_validates_callback_token(): void
    {
        config(['services.xendit.callback_token' => 'xendit-valid-secret-token-999']);

        $user = User::factory()->create(['role' => UserRole::Customer]);
        $user->assignRole(UserRole::Customer->value);

        $category = Category::create(['name' => 'Aki', 'slug' => 'aki']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Aki GS Astra',
            'slug' => 'aki-gs-astra',
            'brand' => 'GS Astra',
            'base_price' => 250000,
            'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'GS-GTZ5S',
            'variant_name' => 'GTZ-5S',
            'stock' => 3,
        ]);

        $order = app(CheckoutService::class)->checkout($user, ['GS-GTZ5S' => 1]);

        // 1. Invalid callback token
        $badResponse = $this->withHeaders([
            'X-Payment-Provider' => 'xendit',
            'x-callback-token' => 'wrong-token',
        ])->postJson('/api/v1/webhooks/payment', [
            'external_id' => $order->order_number,
            'status' => 'PAID',
            'id' => 'XEN-12345',
        ]);

        $badResponse->assertStatus(401)
            ->assertJsonPath('error_code', 'INVALID_WEBHOOK_SIGNATURE');

        // 2. Valid callback token
        $goodResponse = $this->withHeaders([
            'X-Payment-Provider' => 'xendit',
            'x-callback-token' => 'xendit-valid-secret-token-999',
        ])->postJson('/api/v1/webhooks/payment', [
            'external_id' => $order->order_number,
            'status' => 'PAID',
            'id' => 'XEN-12345',
        ]);

        $goodResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(PaymentStatus::Paid, $order->fresh()->payment_status);
    }
}
