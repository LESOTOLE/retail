# Web Admin Console (/admin) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun antarmuka web Single-Page Admin Console (`/admin`) modern dan reaktif yang menghubungkan seluruh REST API operasional MotoVault (Katalog, Stok Multi-Gudang, Pesanan/Resi, Transfer Antar-Gudang, AI Chat Audit, dan Laporan Penjualan).

**Architecture:** Menggunakan Laravel Blade template interaktif berbalut Tailwind CSS dark theme (`#090d16`), terhidrasi dengan data master awal dari server, dan berkomunikasi dengan REST API `/api/v1/admin/*` menggunakan token Bearer Laravel Sanctum.

**Tech Stack:** Laravel 11, Blade, Tailwind CSS, Vanilla JS Fetch API, Laravel Sanctum, PHPUnit / Pest.

## Global Constraints
- Seluruh endpoint API yang digunakan harus sesuai dengan rute terdaftar di `routes/api.php` dan kontrak respon standar `ApiResponse`.
- Desain konsisten dengan UI `resources/views/pos.blade.php` dan `resources/views/welcome.blade.php` (dark mode, warna `#090d16`, font *Plus Jakarta Sans* dan *JetBrains Mono*).
- Setiap form input wajib memiliki validasi sisi klien dan menangani respon error HTTP 422 secara ramah dengan toast notification.
- Seluruh automated feature & unit tests yang ada sebelumnya (78 tests) harus tetap lulus 100% tanpa regresi.

---

### Task 1: Web Route & Admin View Scaffolding + Feature Test

**Files:**
- Create: `resources/views/admin/index.blade.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/AdminWebConsoleTest.php`

**Interfaces:**
- Consumes: Models `Category`, `Warehouse`, `Vehicle`, `Product`, `Order`, `ProductVariant`, `StockTransfer`
- Produces: Web route `GET /admin` yang mengembalikan view `admin.index` dengan parameter `$categories`, `$warehouses`, `$vehicles`, `$stats`

- [ ] **Step 1: Tulis feature test awal untuk route `/admin`**

Buat berkas `tests/Feature/AdminWebConsoleTest.php`:
```php
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
        $response->assertSee('Pesanan & Logistik');
        $response->assertSee('Pergudangan & Stok');
        $response->assertSee('Audit Log AI');
        $response->assertViewHasAll(['categories', 'warehouses', 'vehicles', 'stats']);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan (Red)**

Run: `php artisan test --filter=AdminWebConsoleTest`  
Expected: FAIL (Route `/admin` returns 404)

- [ ] **Step 3: Daftarkan route `GET /admin` di `routes/web.php`**

Perbarui `routes/web.php` untuk menambahkan rute `/admin` dengan hidrasi master data:
```php
Route::get('/admin', function () {
    $categories = Category::orderBy('name')->get();
    $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
    $vehicles = Vehicle::orderBy('brand')->orderBy('model')->get();
    
    $stats = [
        'products_count' => Product::count(),
        'orders_count' => \App\Models\Order::count(),
        'low_stock_count' => \App\Models\ProductVariant::whereColumn('stock', '<=', 'min_stock_alert')->count(),
        'transfers_count' => \App\Models\StockTransfer::where('status', 'dispatched')->count(),
    ];

    return view('admin.index', compact('categories', 'warehouses', 'vehicles', 'stats'));
})->name('admin.index');
```

- [ ] **Step 4: Buat struktur dasar `resources/views/admin/index.blade.php`**

Buat file `resources/views/admin/index.blade.php` dengan layout header, sidebar tab navigation, dan container view.

- [ ] **Step 5: Jalankan test kembali untuk memverifikasi kelulusan (Green)**

Run: `php artisan test --filter=AdminWebConsoleTest`  
Expected: PASS

- [ ] **Step 6: Commit**

Run: `git add routes/web.php resources/views/admin/index.blade.php tests/Feature/AdminWebConsoleTest.php; git commit -m "feat: add admin console route scaffolding and initial test"`

---

### Task 2: Client-Side Auth Wrapper, 1-Click Demo Login & Tab 1 (Overview & KPI)

**Files:**
- Modify: `resources/views/admin/index.blade.php`
- Modify: `tests/Feature/AdminWebConsoleTest.php`

**Interfaces:**
- Consumes: `POST /api/v1/auth/login`, `GET /api/v1/auth/me`, `GET /api/v1/admin/orders`
- Produces: Helper `AdminApi`, session authentication, dan tampilan interaktif KPI & pesanan terkini di Tab 1.

- [ ] **Step 1: Tambahkan test assertion untuk elemen Auth dan Tab Overview**

Uji bahwa halaman memiliki tombol demo login dan kontainer ringkasan statistik KPI.

- [ ] **Step 2: Implementasikan script `AdminApi` dan modul Auth di `resources/views/admin/index.blade.php`**

- Menyimpan dan mengambil Bearer Token dari `localStorage`.
- Fungsi `loginAs(email, role)` untuk 1-click login Super Admin (`admin@motovault.test`) & Staff Gudang (`staff@motovault.test`).
- Menampilkan nama pengguna yang aktif dan badge role.
- Fungsi switch tab: `switchTab('overview')`, `switchTab('products')`, `switchTab('orders')`, `switchTab('warehouse')`, `switchTab('ai-audit')`, `switchTab('reports')`.

- [ ] **Step 3: Implementasikan Tab 1: Overview & KPI Dashboard**

- Card metrik: Total Omset, Total Pesanan, Varian Low-Stock, Transfer Aktif.
- Tabel 5 pesanan terbaru dengan pemanggilan AJAX ke `/api/v1/admin/orders?limit=5`.
- Tombol aksi cepat (*quick action shortcuts*) yang langsung mengarahkan ke tab terkait.

- [ ] **Step 4: Jalankan verifikasi test**

Run: `php artisan test --filter=AdminWebConsoleTest`  
Expected: PASS

- [ ] **Step 5: Commit**

Run: `git add resources/views/admin/index.blade.php tests/Feature/AdminWebConsoleTest.php; git commit -m "feat: add admin api auth helper and overview kpi tab"`

---

### Task 3: Tab 2 (Katalog Produk, Multi-Varian & Kompatibilitas Motor)

**Files:**
- Modify: `resources/views/admin/index.blade.php`
- Modify: `tests/Feature/AdminWebConsoleTest.php`

**Interfaces:**
- Consumes:
  - `GET /api/v1/products`
  - `POST /api/v1/admin/products`
  - `PUT /api/v1/admin/products/{id}`
  - `DELETE /api/v1/admin/products/{id}`
  - `POST /api/v1/admin/products/{id}/variants`
  - `POST /api/v1/admin/products/{id}/compatibility`
- Produces: Manajemen katalog lengkap dengan modal CRUD master produk, modal tambah SKU varian, dan modal pemetaan pivot kendaraan.

- [ ] **Step 1: Tambahkan test assertion untuk komponen form & modal katalog**

Pastikan modal `modal-product-form`, `modal-variant-form`, dan `modal-compat-form` ada di dalam DOM.

- [ ] **Step 2: Implementasikan Tab 2 pada `resources/views/admin/index.blade.php`**

- Search filter produk dan dropdown filter kategori.
- Tabel produk interaktif: Gambar, Brand/Nama, Kategori, Daftar Varian (SKU + Harga + Stok), Jumlah Motor Kompatibel, dan tombol aksi.
- Modal Form Master Produk (Tambah & Edit):
  - Kategori, Brand, Nama Produk, Deskripsi, Image URL, Is Active checkbox.
- Modal Tambah Varian Baru:
  - SKU Barcode, Nama Varian, Additional Price, Stok Awal, Min Stock Alert.
- Modal Kompatibilitas Motor:
  - Multiselect kendaraan dari daftar master motor.
  - Input catatan teknis pemasangan (*compatibility notes*).

- [ ] **Step 3: Jalankan verifikasi test**

Run: `php artisan test --filter=AdminWebConsoleTest`  
Expected: PASS

- [ ] **Step 4: Commit**

Run: `git add resources/views/admin/index.blade.php tests/Feature/AdminWebConsoleTest.php; git commit -m "feat: implement product catalog and vehicle compatibility management tab"`

---

### Task 4: Tab 3 (Pesanan Omnichannel, Detail Order & Logistik Resi Pengiriman)

**Files:**
- Modify: `resources/views/admin/index.blade.php`
- Modify: `tests/Feature/AdminWebConsoleTest.php`

**Interfaces:**
- Consumes:
  - `GET /api/v1/admin/orders`
  - `PATCH /api/v1/admin/orders/{order_number}/fulfill`
  - `POST /api/v1/admin/shipping/create-awb`
- Produces: Pengawasan pesanan omnichannel, detail item belanja, input resi manual, dan trigger otomatis pembuatan resi 3PL.

- [ ] **Step 1: Tambahkan test assertion untuk komponen pesanan dan modal fulfillment**

- [ ] **Step 2: Implementasikan Tab 3 pada `resources/views/admin/index.blade.php`**

- Tab filter status pesanan (`Semua`, `Unpaid`, `Paid`, `Processing`, `Shipped`, `Delivered`, `Cancelled`).
- Tabel pesanan komprehensif: Nomor Order, Customer, Total Tagihan, Metode Bayar, Status Bayar, Status Pengiriman, Tanggal Order.
- Modal Detail Pesanan: Menampilkan data pemesan, alamat kirim, rincian produk, subtotal & ongkir.
- Modal Input Resi Manual: Input `tracking_number` kurir dan trigger pengubahan status ke `shipped`.
- Tombol Aksi Satu-Klik "Generate 3PL AWB": Memanggil API `POST /api/v1/admin/shipping/create-awb`.

- [ ] **Step 3: Jalankan verifikasi test**

Run: `php artisan test --filter=AdminWebConsoleTest`  
Expected: PASS

- [ ] **Step 4: Commit**

Run: `git add resources/views/admin/index.blade.php tests/Feature/AdminWebConsoleTest.php; git commit -m "feat: implement orders fulfillment and logistics waybill tab"`

---

### Task 5: Tab 4 (Pergudangan, Peringatan Stok Rendah & Transfer Antar-Gudang)

**Files:**
- Modify: `resources/views/admin/index.blade.php`
- Modify: `tests/Feature/AdminWebConsoleTest.php`

**Interfaces:**
- Consumes:
  - `GET /api/v1/admin/variants/low-stock`
  - `PUT /api/v1/admin/variants/{id}/stock`
  - `GET /api/v1/admin/warehouses/transfers`
  - `POST /api/v1/admin/warehouses/transfers`
  - `PATCH /api/v1/admin/warehouses/transfers/{id}/status`
- Produces: Pengawasan stok kritis (*safety stock alert*), penyesuaian kuantitas stok manual, dan alur transfer stok antar cabang (*stock transfers*).

- [ ] **Step 1: Tambahkan test assertion untuk komponen pergudangan dan transfer**

- [ ] **Step 2: Implementasikan Tab 4 pada `resources/views/admin/index.blade.php`**

- Sub-navigasi: **Peringatan Stok Menipis** vs **Transfer Antar-Gudang**.
- **Bagian Stok Menipis:**
  - Tabel SKU dengan indikator stok merah/kuning saat `stock <= min_stock_alert`.
  - Modal Penyesuaian Stok Cepat (`stock_adjustment`).
- **Bagian Transfer Stok:**
  - Tabel daftar transfer stok (Nomor transfer, Gudang Asal, Gudang Tujuan, Varian, Unit, Status Badge).
  - Modal Buat Transfer Stok Baru: Form pilih gudang asal, gudang tujuan, varian, dan jumlah unit.
  - Tombol aksi siklus status transfer: `Dispatch` (berangkatkan), `Receive` (terima di tujuan), `Cancel` (batalkan).

- [ ] **Step 3: Jalankan verifikasi test**

Run: `php artisan test --filter=AdminWebConsoleTest`  
Expected: PASS

- [ ] **Step 4: Commit**

Run: `git add resources/views/admin/index.blade.php tests/Feature/AdminWebConsoleTest.php; git commit -m "feat: implement warehouse stock alerts and stock transfers tab"`

---

### Task 6: Tab 5 (Audit Log AI Gemini) & Tab 6 (Laporan Penjualan CSV) + Integrasi Navbar

**Files:**
- Modify: `resources/views/admin/index.blade.php`
- Modify: `resources/views/welcome.blade.php`
- Modify: `resources/views/pos.blade.php`
- Modify: `tests/Feature/AdminWebConsoleTest.php`

**Interfaces:**
- Consumes:
  - `GET /api/v1/admin/ai/logs`
  - `GET /api/v1/admin/reports/sales`
  - `GET /api/v1/admin/reports/sales/export`
- Produces: Audit trail interaksi asisten AI, rekapitulasi penjualan, unduh laporan CSV, serta tombol tautan navigasi lintas modul di Storefront dan POS.

- [ ] **Step 1: Implementasikan Tab 5 (Audit Log AI Gemini)**

- Tabel log sesi chat: Sesi ID, User, Motor yang Dikonsultasikan, Prompt Cuplikan, Waktu Respon, Tool Calls Terpanggil, Konversi Beli.
- Modal Detail Sesi: Inspeksi riwayat chat lengkap dan payload tool calling database.

- [ ] **Step 2: Implementasikan Tab 6 (Laporan Penjualan & Ekspor CSV)**

- Filter rentang tanggal & pilihan gudang cabang.
- Ringkasan omset dan metode pembayaran terpilih.
- Tombol satu-klik "Unduh Laporan Penjualan (CSV)".

- [ ] **Step 3: Tambahkan navigasi menuju `/admin` pada Storefront (`welcome.blade.php`) dan POS (`pos.blade.php`)**

- Tautkan tombol "Admin Console" pada header Storefront & POS agar penguji/pengguna dapat berpindah halaman dengan mudah.

- [ ] **Step 4: Jalankan verifikasi test**

Run: `php artisan test --filter=AdminWebConsoleTest`  
Expected: PASS

- [ ] **Step 5: Commit**

Run: `git add resources/views/admin/index.blade.php resources/views/welcome.blade.php resources/views/pos.blade.php tests/Feature/AdminWebConsoleTest.php; git commit -m "feat: complete ai audit log, sales report export tab, and cross-nav links"`

---

### Task 7: Full Regression Test & Final Polish

**Files:**
- Test: Seluruh test suite `tests/`

- [ ] **Step 1: Jalankan seluruh test suite otomatis**

Run: `php artisan test`  
Expected: 100% tests pass (78+ tests).

- [ ] **Step 2: Verifikasi status git bersih**

Run: `git status`  
Expected: Clean working tree.
