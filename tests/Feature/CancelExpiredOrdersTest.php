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
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CancelExpiredOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_artisan_command_cancels_unpaid_orders_older_than_ttl_and_restores_stock(): void
    {
        $user = User::factory()->create(['role' => UserRole::Customer]);
        $user->assignRole(UserRole::Customer->value);

        $category = Category::create(['name' => 'Oli', 'slug' => 'oli']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Motul 3100',
            'slug' => 'motul-3100',
            'brand' => 'Motul',
            'base_price' => 85000,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'MOT-3100',
            'variant_name' => '0.8L',
            'stock' => 10,
        ]);

        $checkoutService = app(CheckoutService::class);

        // 1. Buat pesanan lama (created_at disetel 35 menit lalu)
        $oldOrder = $checkoutService->checkout($user, ['MOT-3100' => 3]);
        $oldOrder->forceFill(['created_at' => now()->subMinutes(35)])->save();
        $this->assertEquals(7, $variant->fresh()->stock);

        // 2. Buat pesanan baru (created_at disetel 10 menit lalu)
        $recentOrder = $checkoutService->checkout($user, ['MOT-3100' => 2]);
        $recentOrder->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $this->assertEquals(5, $variant->fresh()->stock);

        // Jalankan artisan command
        $this->artisan('orders:cancel-expired', ['--ttl' => 30])
            ->expectsOutputToContain('Sukses membatalkan 1 pesanan kedaluwarsa')
            ->assertSuccessful();

        // Verifikasi: Order lama dibatalkan & status Expired
        $this->assertEquals(PaymentStatus::Expired, $oldOrder->fresh()->payment_status);
        $this->assertEquals(FulfillmentStatus::Cancelled, $oldOrder->fresh()->fulfillment_status);

        // Verifikasi: Order baru tetap Unpaid & Pending
        $this->assertEquals(PaymentStatus::Unpaid, $recentOrder->fresh()->payment_status);
        $this->assertEquals(FulfillmentStatus::Pending, $recentOrder->fresh()->fulfillment_status);

        // Verifikasi: Stok dikembalikan dari pesanan lama (5 + 3 = 8)
        $this->assertEquals(8, $variant->fresh()->stock);
    }
}
