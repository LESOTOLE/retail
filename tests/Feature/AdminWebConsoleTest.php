<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWebConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    public function test_admin_console_route_returns_ok_with_master_data(): void
    {
        $response = $this->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('MotoVault Admin');
        $response->assertSee('Overview');
        $response->assertSee('Katalog Produk');
        $response->assertSee('Pesanan & Logistik', false);
        $response->assertSee('Pergudangan & Stok', false);
        $response->assertSee('Audit Log AI');
        $response->assertViewHasAll(['categories', 'warehouses', 'vehicles', 'stats']);
    }

    public function test_admin_console_has_auth_and_operational_modals(): void
    {
        $response = $this->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Login Super Admin');
        $response->assertSee('Login Staff');
        $response->assertSee('modal-product');
        $response->assertSee('modal-variant');
        $response->assertSee('modal-compat');
        $response->assertSee('modal-order-detail');
        $response->assertSee('modal-fulfill');
        $response->assertSee('modal-stock-adjust');
        $response->assertSee('modal-create-transfer');
    }
}
