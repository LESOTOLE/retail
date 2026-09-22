<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected User $customer;
    protected Warehouse $centralWh;
    protected Warehouse $branchWh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->admin->assignRole(UserRole::Admin->value);

        $this->staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->staff->assignRole(UserRole::Staff->value);

        $this->customer = User::factory()->create(['role' => UserRole::Customer]);
        $this->customer->assignRole(UserRole::Customer->value);

        $this->centralWh = Warehouse::create([
            'code' => 'WH-PST',
            'name' => 'Gudang Pusat SCBD',
            'address' => 'SCBD Jakarta',
            'city' => 'Jakarta Selatan',
            'postal_code' => '12190',
            'latitude' => -6.22,
            'longitude' => 106.80,
            'is_active' => true,
            'is_central' => true,
        ]);

        $this->branchWh = Warehouse::create([
            'code' => 'WH-BDG',
            'name' => 'Cabang Dago Bandung',
            'address' => 'Dago',
            'city' => 'Bandung',
            'postal_code' => '40132',
            'latitude' => -6.89,
            'longitude' => 107.61,
            'is_active' => true,
            'is_central' => false,
        ]);

        $category = Category::create(['name' => 'Pelumas', 'slug' => 'pelumas']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Oli Shell Advance Ultra',
            'slug' => 'oli-shell-advance-ultra',
            'brand' => 'Shell',
            'base_price' => 110000,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'SHL-ADV-1L',
            'variant_name' => '1 Liter',
            'stock' => 100,
            'min_stock_alert' => 10,
        ]);

        // Order 1: Central Warehouse, Online Midtrans, Paid
        $order1 = Order::create([
            'order_number' => 'MV-ORD-20260921-0001',
            'user_id' => $this->customer->id,
            'warehouse_id' => $this->centralWh->id,
            'total_amount' => 220000,
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Processing,
            'payment_method' => 'midtrans_snap',
            'shipping_address' => 'Jl. Sudirman No. 5 Jakarta',
        ]);
        $order1->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 110000,
            'subtotal' => 220000,
        ]);

        // Order 2: Branch Warehouse, POS Cash, Paid
        $order2 = Order::create([
            'order_number' => 'MV-ORD-20260921-0002',
            'user_id' => $this->staff->id,
            'warehouse_id' => $this->branchWh->id,
            'total_amount' => 110000,
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Delivered,
            'payment_method' => 'CASH',
            'shipping_address' => 'POS In-Store: Walk-in Customer',
        ]);
        $order2->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 110000,
            'subtotal' => 110000,
        ]);
    }

    public function test_staff_and_admin_can_view_sales_summary_and_breakdowns(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')->getJson('/api/v1/admin/reports/sales');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_omset', 330000)
            ->assertJsonPath('data.summary.total_transaksi', 2)
            ->assertJsonPath('data.summary.total_unit_terjual', 3);

        $this->assertCount(2, $response->json('data.summary.omset_per_metode'));
        $this->assertCount(2, $response->json('data.summary.omset_per_gudang'));
    }

    public function test_customer_cannot_access_sales_reports(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/admin/reports/sales');

        $response->assertStatus(403);
    }

    public function test_filter_sales_report_by_specific_warehouse(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/reports/sales?warehouse_id=' . $this->branchWh->id);

        $response->assertOk()
            ->assertJsonPath('data.summary.total_omset', 110000)
            ->assertJsonPath('data.summary.total_transaksi', 1)
            ->assertJsonPath('data.summary.total_unit_terjual', 1);
    }

    public function test_export_sales_csv_returns_streamed_file_with_headers_and_rows(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')->get('/api/v1/admin/reports/sales/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="laporan-penjualan-motovault-', (string) $response->headers->get('Content-Disposition'));

        $content = $response->getContent();

        // Verify CSV header columns
        $this->assertStringContainsString('"Tanggal Transaksi"', $content);
        $this->assertStringContainsString('"No. Pesanan"', $content);
        $this->assertStringContainsString('"Cabang Gudang"', $content);
        $this->assertStringContainsString('"Metode Pembayaran"', $content);

        // Verify rows contain orders
        $this->assertStringContainsString('MV-ORD-20260921-0001', $content);
        $this->assertStringContainsString('MV-ORD-20260921-0002', $content);
        $this->assertStringContainsString('Gudang Pusat SCBD', $content);
        $this->assertStringContainsString('Cabang Dago Bandung', $content);
        $this->assertStringContainsString('POS Kasir Toko', $content);
        $this->assertStringContainsString('Storefront Online', $content);
    }

    public function test_web_pos_export_route_downloads_csv(): void
    {
        $response = $this->get('/pos/reports/sales/export?warehouse_id=' . $this->centralWh->id);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('MV-ORD-20260921-0001', $content);
        $this->assertStringNotContainsString('MV-ORD-20260921-0002', $content);
    }
}
