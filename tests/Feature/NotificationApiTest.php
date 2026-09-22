<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_recent_notifications_endpoint_returns_orders_and_low_stock_alerts(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $customer->assignRole(UserRole::Customer->value);

        $category = Category::create(['name' => 'Kelistrikan', 'slug' => 'kelistrikan']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Busi Iridium Racing CR8EIX',
            'slug' => 'busi-iridium-cr8eix',
            'brand' => 'NGK',
            'base_price' => 125000,
            'is_active' => true,
        ]);

        // Varian dengan stok normal (tidak memicu alert)
        $variantNormal = $product->variants()->create([
            'sku' => 'NGK-CR8-NORM',
            'variant_name' => 'Standard Box',
            'stock' => 50,
            'min_stock_alert' => 5,
        ]);

        // Varian dengan stok rendah (memicu alert)
        $variantLow = $product->variants()->create([
            'sku' => 'NGK-CR8-LOW',
            'variant_name' => 'Blister Pack',
            'stock' => 2,
            'min_stock_alert' => 5,
        ]);

        // Buat Order
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'user_id' => $customer->id,
            'total_amount' => 125000,
            'payment_status' => PaymentStatus::Unpaid,
            'payment_method' => 'midtrans_snap',
        ]);

        $response = $this->getJson('/api/v1/notifications/recent');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reverb_config.app_key', config('broadcasting.connections.reverb.key', 'motovault-key'))
            ->assertJsonPath('data.recent_orders.0.order_number', $order->order_number)
            ->assertJsonPath('data.recent_orders.0.payment_status', 'unpaid')
            ->assertJsonPath('data.low_stock_alerts.0.sku', 'NGK-CR8-LOW')
            ->assertJsonPath('data.low_stock_alerts.0.current_stock', 2);

        $this->assertGreaterThanOrEqual(1, $response->json('data.unread_count'));
    }

    public function test_pos_terminal_view_renders_echo_scripts_and_ui_containers(): void
    {
        $response = $this->get('/pos');

        $response->assertOk()
            ->assertSee('pusher.min.js', false)
            ->assertSee('echo.iife.js', false)
            ->assertSee('ws-status-badge', false)
            ->assertSee('pos-toast-container', false)
            ->assertSee('SoundFx', false)
            ->assertSee('OrderCreated', false);
    }

    public function test_storefront_view_renders_echo_realtime_listener(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('echo.iife.js', false)
            ->assertSee('storeEcho', false)
            ->assertSee('OrderStatusUpdated', false);
    }
}
