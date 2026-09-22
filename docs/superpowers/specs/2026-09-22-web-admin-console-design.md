# Design Specification: MotoVault Web Admin Console (/admin)

**Document:** `docs/superpowers/specs/2026-09-22-web-admin-console-design.md`  
**Date:** 2026-09-22  
**Status:** Approved / Ready for Implementation  
**Target:** Web Admin Console Single-Page Interface (`/admin`) for MotoVault Omnichannel Platform  

---

## 1. Objectives & Overview
Membangun antarmuka terpadu Single-Page Web Admin Console (`/admin`) berbasis Blade, Tailwind CSS, dan JavaScript reaktif yang berinteraksi langsung dengan REST API `/api/v1/admin/*` dan `/api/v1/*` menggunakan Laravel Sanctum Bearer Token.

Antarmuka ini memberikan kontrol operasional penuh bagi **Super Admin** dan **Staff Gudang / Operasional** untuk:
1. Memantau KPI utama bisnis dan metrik kesehatan sistem secara real-time.
2. Mengelola katalog produk, SKU varian, stok, dan relasi matriks kompatibilitas motor.
3. Memantau status pesanan omnichannel, detail item, input nomor resi pengiriman kurir, dan pembuatan resi AWB 3PL otomatis.
4. Memantau stok menipis (*low stock alert*), penyesuaian stok opname (*stock adjustment*), serta pembuatan dan pelacakan transfer stok antar-gudang (*stock transfers*).
5. Mengaudit log percakapan asisten Gemini AI, eksekusi *tool calling*, dan tingkat konversi penjualan.
6. Mengakses rekapitulasi laporan penjualan dan mengunduh berkas CSV terstandarisasi.

---

## 2. System Architecture & Routing

### 2.1. Web Route Entry Point
- **Route:** `GET /admin` pada [`routes/web.php`](file:///D:/project/motovault/routes/web.php)
- **Controller/Closure:** Me-render view `resources/views/admin/index.blade.php`
- **Preloaded Master Data (Hydration):**
  - `$categories = Category::orderBy('name')->get();`
  - `$warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();`
  - `$vehicles = Vehicle::orderBy('brand')->orderBy('model')->get();`
  - `$stats = [ 'products_count', 'orders_count', 'low_stock_count', 'transfers_count' ];`

### 2.2. Client-Side API Communication & Authentication
- **Token Storage:** Disimpan di browser pada `localStorage.getItem('motovault_admin_token')` dan info pengguna `motovault_admin_user`.
- **1-Click Demo Login:**
  - Super Admin: `admin@motovault.test` (password: `password`)
  - Staff Gudang: `staff@motovault.test` (password: `password`)
  - Tombol aksi cepat untuk mengisi kredensial dan melakukan autentikasi ke `POST /api/v1/auth/login`.
- **API Client Wrapper (`AdminApi`):**
  - Menangani seluruh request HTTP (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`).
  - Menambahkan header `Authorization: Bearer <token>` dan `Accept: application/json`.
  - Menangani error otomatis (jika HTTP 401 Unauthorized, hapus token dan tampilkan modal login; jika error validasi HTTP 422, tampilkan toast pesan error).

---

## 3. UI Component & Tab Navigation Layout

### 3.1. Navigation & Header
- **Top Header:**
  - MotoVault Admin Logo & Version Badge (`v1.2 Omnichannel Admin`).
  - Quick Links: Kembali ke Storefront (`/`) & Kasir POS (`/pos`).
  - Status koneksi API & indikator akun aktif (Nama Admin, Badge Role: `admin` / `staff`).
  - Tombol Logout (membersihkan token dari client & memanggil `POST /api/v1/auth/logout`).
- **Sidebar Tabs:**
  1. `tab-overview`: **Overview & KPI** (Ringkasan performa toko & metrik utama)
  2. `tab-products`: **Katalog & Kompatibilitas** (CRUD Master Produk, Varian, Pivot Kendaraan)
  3. `tab-orders`: **Pesanan & Logistik** (Monitoring Pesanan, Resi Pengiriman, Generate AWB)
  4. `tab-warehouse`: **Pergudangan & Transfer Stok** (Low Stock Alerts, Penyesuaian Stok, Inter-Warehouse Transfers)
  5. `tab-ai-audit`: **Audit Log AI** (Histori Percakapan Gemini, Tool Calls, Konversi)
  6. `tab-reports`: **Laporan Penjualan** (Filter & Unduh CSV)

---

## 4. Detailed Tab Specifications

### Tab 1: Overview & KPI Metrics
- **Cards KPI:**
  - Total Omset / Pendapatan Penjualan Terverifikasi (`paid` & `delivered`).
  - Total Pesanan Masuk (dengan rincian `unpaid`, `processing`, `shipped`, `delivered`).
  - Peringatan Stok Kritis (Jumlah varian dengan `stock <= min_stock_alert`).
  - Transfer Stok Dalam Perjalanan (`transfers` berstatus `dispatched`).
- **Aksi Cepat (Quick Action Shortcuts):**
  - "Tambah Produk Baru", "Buat Transfer Stok", "Input Resi Pengiriman", "Unduh Rekap CSV".
- **Aktivitas Pesanan Terbaru:** Tabel mini 5 pesanan terakhir dengan status berwarna.

### Tab 2: Katalog Produk & Kompatibilitas
- **Pencarian & Filter:** Search by name/brand, filter by category, filter by status active.
- **Tabel Produk:** Kolom Gambar/Icon, Brand & Judul, Kategori, Jumlah Varian, Kompatibilitas Motor, Status, dan Tombol Aksi (Edit, Varian, Kompatibilitas, Hapus).
- **Modal Tambah/Edit Master Produk:**
  - Form field: `category_id`, `name`, `brand`, `description`, `image_url`, `is_active`.
  - Endpoint: `POST /api/v1/admin/products` & `PUT /api/v1/admin/products/{id}`.
- **Modal Tambah Varian Produk:**
  - Form field: `sku`, `name`, `additional_price`, `stock`, `min_stock_alert`.
  - Endpoint: `POST /api/v1/admin/products/{id}/variants`.
- **Modal Sinkronisasi Kompatibilitas Motor:**
  - Memilih motor dari daftar master `vehicles`.
  - Input catatan teknis (misal: "Plug and play", "Memerlukan bracket kaliper 260mm").
  - Endpoint: `POST /api/v1/admin/products/{id}/compatibility`.

### Tab 3: Pesanan & Logistik Resi
- **Filter Pesanan:** Filter berdasarkan status (`all`, `unpaid`, `paid`, `processing`, `shipped`, `delivered`, `cancelled`).
- **Tabel Pesanan:** Nomor Pesanan (`order_number`), Pembeli/Customer, Total Pembayaran, Metode Bayar, Status Bayar, Status Fulfillment, Tanggal, dan Aksi.
- **Modal Detail Pesanan:**
  - Rincian item yang dibeli, harga satuan, subtotal, alamat pengiriman penerima, kurir yang dipilih, dan riwayat status.
- **Modal Input Resi Pengiriman:**
  - Input `tracking_number` kurir (JNE, J&T, SiCepat, dll.).
  - Mengubah status pesanan ke `shipped`.
  - Endpoint: `PATCH /api/v1/admin/orders/{order_number}/fulfill`.
- **Aksi Buat Resi 3PL (Generate AWB):**
  - Tombol satu-klik untuk memanggil endpoint `POST /api/v1/admin/shipping/create-awb`.
  - Menghasilkan nomor resi otomatis dan mengupdate status pesanan.

### Tab 4: Pergudangan & Transfer Antar-Gudang
- **Sub-Tab / Bagian A: Peringatan Stok Rendah (Low-Stock Alert)**
  - Mengambil data dari `GET /api/v1/admin/variants/low-stock`.
  - Menampilkan SKU, Nama Varian, Stok Saat Ini vs Batas Minimum (`min_stock_alert`).
  - Tombol aksi cepat: "Sesuaikan Stok" yang membuka modal `PUT /api/v1/admin/variants/{id}/stock`.
- **Sub-Tab / Bagian B: Transfer Stok Antar-Gudang (Stock Transfers)**
  - Mengambil data dari `GET /api/v1/admin/warehouses/transfers`.
  - Menampilkan Nomor Transfer (`transfer_number`), Gudang Asal, Gudang Tujuan, Varian, Jumlah Unit, Status (`draft`, `dispatched`, `received`, `cancelled`).
  - **Form / Modal Buat Transfer:**
    - Pilih gudang asal (`from_warehouse_id`), gudang tujuan (`to_warehouse_id`), varian produk, dan jumlah unit (`quantity`).
    - Endpoint: `POST /api/v1/admin/warehouses/transfers`.
  - **Aksi Status Transfer:**
    - Tombol "Dispatch" (berangkatkan barang dari gudang asal) -> `PATCH .../status` dengan `status: dispatched`.
    - Tombol "Receive" (konfirmasi penerimaan barang di gudang tujuan) -> `PATCH .../status` dengan `status: received`.
    - Tombol "Cancel" (batalkan transfer dan kembalikan stok) -> `PATCH .../status` dengan `status: cancelled`.

### Tab 5: Audit Log Percakapan AI
- **Sumber Data:** `GET /api/v1/admin/ai/logs`
- **Tabel Sesi:** ID Sesi, Customer/User, Model Motor yang Ditanyakan, Ringkasan Prompt, Durasi Eksekusi Gemini, Jumlah Tool Calls, dan Status Konversi (`has_order` / `conversion`).
- **Modal Detail Percakapan:**
  - Riwayat pesan obrolan antara pelanggan dan AI.
  - JSON inspeksi tool calls yang dieksekusi (contoh: `search_products`, `check_compatibility`, `get_product_detail`).
  - Rekomendasi produk nyata dari database yang disajikan kepada pelanggan.

### Tab 6: Laporan Penjualan & Ekspor CSV
- **Filter Rentang Tanggal & Gudang:** Tanggal mulai, tanggal selesai, filter gudang cabang.
- **Ringkasan:** Total Omset, Total Transaksi, Unit Terjual, dan Breakdown Metode Pembayaran.
- **Tombol Unduh CSV:** Mengarahkan ke endpoint `GET /api/v1/admin/reports/sales/export` dengan parameter filter.

---

## 5. Security, Roles & Error Handling
1. **Role Access:**
   - Fitur CRUD Master Produk, Hapus Produk, dan Sinkronisasi Kompatibilitas memerlukan role `admin`.
   - Fitur Penyesuaian Stok, Input Resi, dan Status Transfer Gudang dapat diakses oleh `staff` dan `admin`.
   - Tampilan UI secara dinamis menyesuaikan tombol aksi berdasarkan role pengguna yang sedang login.
2. **Graceful Handling & Notifications:**
   - Toast notification interaktif (hijau untuk sukses, merah untuk error, kuning untuk warning) saat aksi selesai dilakukan.
   - Konfirmasi dialog (*confirm dialog*) sebelum melakukan aksi destruktif seperti pembatalan transfer atau penghapusan produk.

---

## 6. Testing Strategy
1. **Automated Feature Test (`tests/Feature/AdminWebConsoleTest.php`):**
   - Menguji route web `GET /admin` dapat diakses dengan sukses (HTTP 200) dan merender seluruh master data awal (kategori, gudang, motor).
   - Memastikan navigasi dan struktur container modal tersedia pada DOM.
   - Memastikan semua endpoint API yang dipanggil dari konsol admin tetap lulus pengujian (regresi 100%).
