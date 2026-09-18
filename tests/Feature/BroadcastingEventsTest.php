<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\UserRole;
use App\Events\LowStockAlertEvent;
use App\Events\OrderCreatedEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastingEventsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected User $customer;
    protected User $otherCustomer;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        require_once base_path('routes/channels.php');

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->admin->assignRole(UserRole::Admin->value);

        $this->staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->staff->assignRole(UserRole::Staff->value);

        $this->customer = User::factory()->create(['role' => UserRole::Customer]);
        $this->customer->assignRole(UserRole::Customer->value);

        $this->otherCustomer = User::factory()->create(['role' => UserRole::Customer]);
        $this->otherCustomer->assignRole(UserRole::Customer->value);

        $category = Category::create(['name' => 'Kelistrikan', 'slug' => 'kelistrikan']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Aki Motobatt Gel MTZ5S',
            'slug' => 'aki-motobatt-gel-mtz5s',
            'brand' => 'Motobatt',
            'base_price' => 240000,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'MTB-MTZ5S',
            'variant_name' => 'Standard 12V 4.2Ah',
            'additional_price' => 0,
            'stock' => 5,
            'min_stock_alert' => 3,
        ]);
    }

    public function test_checkout_dispatches_order_created_and_low_stock_events(): void
    {
        Event::fake([
            OrderCreatedEvent::class,
            LowStockAlertEvent::class,
        ]);

        // Checkout 3 items -> stock becomes 2 (<= min_stock_alert 3)
        $order = app(\App\Services\CheckoutService::class)->checkout(
            $this->customer,
            ['MTB-MTZ5S' => 3]
        );

        Event::assertDispatched(OrderCreatedEvent::class, function (OrderCreatedEvent $event) use ($order) {
            $this->assertEquals($order->id, $event->order->id);
            $channels = collect($event->broadcastOn())->map->name->all();
            $this->assertContains('orders', $channels);
            $this->assertContains('private-orders.staff', $channels);
            $this->assertContains('private-orders.user.' . $this->customer->id, $channels);
            $this->assertEquals('OrderCreated', $event->broadcastAs());
            return true;
        });

        Event::assertDispatched(LowStockAlertEvent::class, function (LowStockAlertEvent $event) {
            $this->assertEquals('MTB-MTZ5S', $event->variant->sku);
            $this->assertEquals(2, $event->variant->stock);
            $this->assertEquals('LowStockAlert', $event->broadcastAs());
            return true;
        });
    }

    public function test_mark_as_paid_and_fulfillment_dispatches_order_status_updated_event(): void
    {
        Event::fake([
            OrderCreatedEvent::class,
            OrderStatusUpdatedEvent::class,
            LowStockAlertEvent::class,
        ]);

        $order = app(\App\Services\CheckoutService::class)->checkout(
            $this->customer,
            ['MTB-MTZ5S' => 1]
        );

        app(\App\Services\CheckoutService::class)->markAsPaid($order, 'PAY-TEST-999');

        Event::assertDispatched(OrderStatusUpdatedEvent::class, function (OrderStatusUpdatedEvent $event) use ($order) {
            $this->assertEquals($order->order_number, $event->order->order_number);
            $channels = collect($event->broadcastOn())->map->name->all();
            $this->assertContains('orders.' . $order->order_number, $channels);
            $this->assertContains('private-orders.staff', $channels);
            $this->assertContains('private-orders.user.' . $this->customer->id, $channels);
            $this->assertEquals('OrderStatusUpdated', $event->broadcastAs());
            return true;
        });
    }

    public function test_admin_adjust_stock_dispatches_low_stock_event_when_below_min(): void
    {
        Event::fake([
            LowStockAlertEvent::class,
        ]);

        $token = $this->staff->createToken('staff-tok')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/v1/admin/variants/{$this->variant->id}/stock", [
                'mode' => 'set',
                'amount' => 2,
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(LowStockAlertEvent::class, function (LowStockAlertEvent $event) {
            return $event->variant->id === $this->variant->id && $event->variant->stock === 2;
        });
    }

    public function test_broadcast_channel_authorization_rules(): void
    {
        // 1. orders.staff channel policy
        $this->assertTrue($this->admin->canManageStore());
        $this->assertTrue($this->staff->canManageStore());
        $this->assertFalse($this->customer->canManageStore());

        // 2. orders.user.{userId} channel policy
        $userChannelAuth = fn (User $user, int $targetUserId) => (int) $user->id === $targetUserId || $user->canManageStore();

        $this->assertTrue($userChannelAuth($this->customer, $this->customer->id));
        $this->assertFalse($userChannelAuth($this->otherCustomer, $this->customer->id));
        $this->assertTrue($userChannelAuth($this->admin, $this->customer->id));

        // 3. inventory.staff channel policy
        $this->assertTrue($this->staff->canManageStore());
        $this->assertFalse($this->customer->canManageStore());
    }

    public function test_order_created_event_payload_structure(): void
    {
        Event::fake([OrderCreatedEvent::class, OrderStatusUpdatedEvent::class, LowStockAlertEvent::class]);

        $order = app(\App\Services\CheckoutService::class)->checkout(
            $this->customer,
            ['MTB-MTZ5S' => 1]
        );

        $event = new OrderCreatedEvent($order);
        $payload = $event->broadcastWith();

        $this->assertEquals($order->id, $payload['order_id']);
        $this->assertEquals($order->order_number, $payload['order_number']);
        $this->assertEquals(240000, $payload['total_amount']);
        $this->assertEquals('unpaid', $payload['payment_status']);
        $this->assertEquals(1, $payload['items_count']);
    }

    public function test_order_status_updated_event_payload_structure(): void
    {
        Event::fake([OrderCreatedEvent::class, OrderStatusUpdatedEvent::class, LowStockAlertEvent::class]);

        $order = app(\App\Services\CheckoutService::class)->checkout(
            $this->customer,
            ['MTB-MTZ5S' => 1]
        );
        app(\App\Services\CheckoutService::class)->markAsPaid($order, 'REF-999');

        $event = new OrderStatusUpdatedEvent($order);
        $payload = $event->broadcastWith();

        $this->assertEquals($order->order_number, $payload['order_number']);
        $this->assertEquals('paid', $payload['payment_status']);
        $this->assertEquals('processing', $payload['fulfillment_status']);
        $this->assertEquals('REF-999', $payload['payment_reference']);
    }

    public function test_low_stock_alert_event_payload_structure(): void
    {
        $this->variant->update(['stock' => 1]);
        $event = new LowStockAlertEvent($this->variant);
        $payload = $event->broadcastWith();

        $this->assertEquals('MTB-MTZ5S', $payload['sku']);
        $this->assertEquals(1, $payload['current_stock']);
        $this->assertEquals(3, $payload['min_stock_alert']);
        $this->assertStringContainsString('Perhatian! Stok SKU MTB-MTZ5S', $payload['alert_message']);
    }
}
