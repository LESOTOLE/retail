<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAndPosApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_create_and_update_product_and_add_variant(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $admin->assignRole(UserRole::Admin->value);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $category = Category::create(['name' => 'Helm', 'slug' => 'helm']);

        // 1. Create Product
        $storeResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/products', [
                'category_id' => $category->id,
                'name' => 'KYT TT Course',
                'brand' => 'KYT',
                'base_price' => 1200000,
                'description' => 'Helm full face sporty',
            ]);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'KYT TT Course');

        $productId = $storeResponse->json('data.id');

        // 2. Add Variant
        $variantResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/products/{$productId}/variants", [
                'sku' => 'KYT-TTC-L',
                'variant_name' => 'Size L',
                'additional_price' => 0,
                'stock' => 12,
                'min_stock_alert' => 3,
            ]);

        $variantResponse->assertStatus(201)
            ->assertJsonPath('data.sku', 'KYT-TTC-L');

        // 3. Update Product
        $updateResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/admin/products/{$productId}", [
                'name' => 'KYT TT Course Replica',
                'base_price' => 1350000,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'KYT TT Course Replica');
    }

    public function test_admin_can_sync_product_compatibility(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $admin->assignRole(UserRole::Admin->value);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $category = Category::create(['name' => 'Rem', 'slug' => 'rem']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Brembo Caliper 4P',
            'slug' => 'brembo-caliper-4p',
            'brand' => 'Brembo',
            'base_price' => 2500000,
        ]);

        $v1 = Vehicle::create(['brand' => 'Honda', 'model' => 'Vario 160', 'year_start' => 2022]);
        $v2 = Vehicle::create(['brand' => 'Yamaha', 'model' => 'NMAX 155', 'year_start' => 2020]);

        $syncResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/products/{$product->id}/compatibility", [
                'vehicles' => [
                    ['vehicle_id' => $v1->id, 'notes' => 'Perlu bracket 260mm'],
                    ['vehicle_id' => $v2->id, 'notes' => 'Perlu bracket 230mm'],
                ],
            ]);

        $syncResponse->assertStatus(200);
        $this->assertCount(2, $product->fresh()->vehicles);
    }

    public function test_staff_can_adjust_variant_stock_and_view_low_stock_alerts(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $staff->assignRole(UserRole::Staff->value);
        $token = $staff->createToken('staff-token')->plainTextToken;

        $category = Category::create(['name' => 'Busi', 'slug' => 'busi']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Busi NGK',
            'slug' => 'busi-ngk',
            'brand' => 'NGK',
            'base_price' => 45000,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'NGK-B9',
            'variant_name' => 'B9',
            'stock' => 10,
            'min_stock_alert' => 5,
        ]);

        // Adjust stock decrement by 8 -> remaining 2 (needs restock)
        $adjustResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/admin/variants/{$variant->id}/stock", [
                'mode' => 'decrement',
                'amount' => 8,
            ]);

        $adjustResponse->assertStatus(200)
            ->assertJsonPath('data.stock', 2)
            ->assertJsonPath('data.needs_restock', true);

        // View low stock
        $lowStockResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/variants/low-stock');

        $lowStockResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'NGK-B9');
    }

    public function test_staff_can_fulfill_order_with_tracking_number(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $staff->assignRole(UserRole::Staff->value);
        $token = $staff->createToken('staff-token')->plainTextToken;

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $customer->assignRole(UserRole::Customer->value);

        $category = Category::create(['name' => 'Oli', 'slug' => 'oli']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Oli Motul',
            'slug' => 'oli-motul',
            'brand' => 'Motul',
            'base_price' => 100000,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'MOT-1L',
            'variant_name' => '1L',
            'stock' => 10,
        ]);

        $order = app(\App\Services\CheckoutService::class)->checkout($customer, ['MOT-1L' => 1]);
        app(\App\Services\CheckoutService::class)->markAsPaid($order, 'TRX-PAY-1');

        $fulfillResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/admin/orders/{$order->order_number}/fulfill", [
                'tracking_number' => 'JNE-123456789',
                'fulfillment_status' => FulfillmentStatus::Shipped->value,
            ]);

        $fulfillResponse->assertStatus(200)
            ->assertJsonPath('data.tracking_number', 'JNE-123456789')
            ->assertJsonPath('data.fulfillment_status', FulfillmentStatus::Shipped->value);
    }

    public function test_cashier_can_perform_pos_direct_bill_checkout(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Staff]);
        $cashier->assignRole(UserRole::Staff->value);
        $token = $cashier->createToken('pos-token')->plainTextToken;

        $category = Category::create(['name' => 'Oli', 'slug' => 'oli']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Oli Motul',
            'slug' => 'oli-motul',
            'brand' => 'Motul',
            'base_price' => 100000,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'MOT-POS',
            'variant_name' => '1L',
            'stock' => 10,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/pos/orders', [
                'payment_method' => 'CASH',
                'items' => [
                    ['sku' => 'MOT-POS', 'quantity' => 2],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.fulfillment_status', FulfillmentStatus::Delivered->value)
            ->assertJsonPath('data.total_amount', 200000);

        $this->assertEquals(8, $variant->fresh()->stock);
    }

    public function test_admin_can_view_ai_chat_audit_logs(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $admin->assignRole(UserRole::Admin->value);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $session = \App\Models\AiChatSession::create([
            'session_token' => 'audit-test-session',
            'user_id' => $admin->id,
        ]);
        $session->pushMessage(\App\Enums\ChatSender::User, 'Halo AI');
        $session->pushMessage(\App\Enums\ChatSender::Assistant, 'Halo Admin');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/ai/logs');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_health_check_endpoint(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'timestamp',
                    'checks' => ['database', 'cache', 'ai_gemini_configured'],
                ],
            ]);
    }
}
