# Task List: Integrasi Midtrans Snap, Pengamanan Webhook & API Rate Limiting

**PRD Rujukan:** [`docs/superpowers/specs/2026-09-21-payment-gateway-security-prd.md`](file:///D:/project/motovault/docs/superpowers/specs/2026-09-21-payment-gateway-security-prd.md)  
**Status Proyek:** Selesai (Completed - All 65 Tests Pass)  

---

## Phase 1: Konfigurasi & Service Midtrans Snap Gateway
- [x] **Task 1.1:** Update Konfigurasi Layanan Midtrans
  - File: [`config/services.php`](file:///D:/project/motovault/config/services.php)
  - Tambahkan konfigurasi `client_key` dan `snap_url` untuk sandbox dan production.
- [x] **Task 1.2:** Buat Service Class `App\Services\Payment\MidtransService`
  - File: [`app/Services/Payment/MidtransService.php`](file:///D:/project/motovault/app/Services/Payment/MidtransService.php)
  - Implementasikan pembuatan payload Snap (`transaction_details`, `item_details`, `customer_details`).
  - Implementasikan panggilan HTTP POST ke Midtrans Snap API via Laravel Http Client.
  - Implementasikan mode fallback lokal jika `MIDTRANS_SERVER_KEY` belum dikonfigurasi.

---

## Phase 2: Pengamanan Digital Signature Webhook Pembayaran
- [x] **Task 2.1:** Tambahkan Logika Verifikasi Signature SHA-512 pada `PaymentWebhookController`
  - File: [`app/Http/Controllers/Api/V1/PaymentWebhookController.php`](file:///D:/project/motovault/app/Http/Controllers/Api/V1/PaymentWebhookController.php)
  - Hitung hash SHA-512: `hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey)`.
  - Tolak dengan HTTP 401 dan error code `INVALID_WEBHOOK_SIGNATURE` jika signature tidak cocok.
  - Tambahkan dukungan validasi token callback Xendit jika provider = xendit.
  - Pertahankan pencatatan idempotensi log dan state machine `paid` / `cancel`.

---

## Phase 3: Implementasi Named Rate Limiting & Throttling
- [x] **Task 3.1:** Konfigurasi Named Rate Limiters di `AppServiceProvider`
  - File: [`app/Providers/AppServiceProvider.php`](file:///D:/project/motovault/app/Providers/AppServiceProvider.php)
  - Daftarkan limiter: `ai-chat` (15/min), `auth` (10/min), `catalog` (120/min), `checkout` (10/min), `webhook` (60/min).
  - Terapkan respon envelope JSON kustom standar `ApiResponse::error` dengan HTTP 429 saat rate limit tercapai.
- [x] **Task 3.2:** Pasang Middleware Throttle pada API Routes
  - File: [`routes/api.php`](file:///D:/project/motovault/routes/api.php)
  - Pasang `throttle:ai-chat` pada `/api/v1/ai/chat` dan `/api/v1/ai/chat/stream`.
  - Pasang `throttle:auth` pada `/api/v1/auth/login` dan `/api/v1/auth/register`.
  - Pasang `throttle:catalog` pada `/api/v1/products` dan `/api/v1/vehicles`.
  - Pasang `throttle:checkout` pada `/api/v1/orders/checkout`.
  - Pasang `throttle:webhook` pada `/api/v1/webhooks/payment`.

---

## Phase 4: Integrasi Checkout Controller & Order API Envelope
- [x] **Task 4.1:** Perbarui `OrderController::checkout` untuk Menghasilkan Snap Payload
  - File: [`app/Http/Controllers/Api/V1/OrderController.php`](file:///D:/project/motovault/app/Http/Controllers/Api/V1/OrderController.php)
  - Inject `MidtransService` ke `OrderController`.
  - Dapatkan `payment_payload` (`snap_token`, `redirect_url`) dari `MidtransService`.
  - Sertakan `payment_payload` dalam respon `ApiResponse::created`.
- [x] **Task 4.2:** Perbarui `OrderResource` jika Diperlukan
  - File: [`app/Http/Resources/OrderResource.php`](file:///D:/project/motovault/app/Http/Resources/OrderResource.php)
  - Format respon order konsisten dengan tambahan payment payload.

---

## Phase 5: Integrasi Antarmuka Frontend (Storefront Snap Popup)
- [x] **Task 5.1:** Muat Script Midtrans Snap di `welcome.blade.php`
  - File: [`resources/views/welcome.blade.php`](file:///D:/project/motovault/resources/views/welcome.blade.php)
  - Sisipkan tag `<script src="https://app.sandbox.midtrans.com/snap/snap.js" ...></script>`.
- [x] **Task 5.2:** Hubungkan Modal Sukses dengan Tombol Bayar Snap
  - File: [`resources/views/welcome.blade.php`](file:///D:/project/motovault/resources/views/welcome.blade.php)
  - Saat `checkoutSuccessData.payment_payload.snap_token` ada, sediakan tombol **"Bayar Sekarang via Midtrans Snap"**.
  - Panggil `window.snap.pay(snapToken)` dan tangani callback `onSuccess`, `onPending`, `onError`.

---

## Phase 6: Automated Test Suite & Verifikasi
- [x] **Task 6.1:** Tulis Test Pengujian Keamanan Webhook & Midtrans
  - File: [`tests/Feature/MidtransPaymentAndWebhookSecurityTest.php`](file:///D:/project/motovault/tests/Feature/MidtransPaymentAndWebhookSecurityTest.php)
  - Uji: Valid signature webhook melunasi order (HTTP 200).
  - Uji: Tampered signature ditolak (HTTP 401 INVALID_WEBHOOK_SIGNATURE).
  - Uji: Checkout menghasilkan `payment_payload` berisi token & redirect url.
- [x] **Task 6.2:** Tulis Test Pengujian Rate Limiting
  - File: [`tests/Feature/RateLimiterApiTest.php`](file:///D:/project/motovault/tests/Feature/RateLimiterApiTest.php)
  - Uji: Request ke-16 ke `/api/v1/ai/chat` ditolak dengan HTTP 429.
  - Uji: Request ke-11 ke `/api/v1/auth/login` ditolak dengan HTTP 429.
- [x] **Task 6.3:** Uji Regresi Penuh
  - Seluruh 65 feature & unit tests lulus tanpa kegagalan (Pass 100%).

---

## Phase 7: Order Auto-Release TTL 30-Menit (Stock Leak Prevention)
- [x] **Task 7.1:** Implementasi Logika `cancelExpiredOrders` di `CheckoutService`
  - File: [`app/Services/CheckoutService.php`](file:///D:/project/motovault/app/Services/CheckoutService.php)
  - Query pesanan berstatus `Unpaid` yang dibuat lebih dari 30 menit lalu (`created_at <= now()->subMinutes($ttl)`).
  - Kembalikan stok varian global dan stok spesifik gudang, ubah status menjadi `Expired` dan `Cancelled`.
- [x] **Task 7.2:** Pembuatan Artisan Command `orders:cancel-expired`
  - File: [`app/Console/Commands/CancelExpiredOrdersCommand.php`](file:///D:/project/motovault/app/Console/Commands/CancelExpiredOrdersCommand.php)
  - Parameter `--ttl=30` opsional, log jumlah pesanan yang dibatalkan.
- [x] **Task 7.3:** Penjadwalan Otomatis di Laravel Scheduler
  - File: [`routes/console.php`](file:///D:/project/motovault/routes/console.php)
  - Daftarkan `Schedule::command('orders:cancel-expired')->everyMinute()`.
- [x] **Task 7.4:** Automated Test untuk TTL Expiration
  - File: [`tests/Feature/CancelExpiredOrdersTest.php`](file:///D:/project/motovault/tests/Feature/CancelExpiredOrdersTest.php)

---

## Phase 8: Sinkronisasi Multi-Warehouse Stock (Dual-Source of Truth Resolution)
- [x] **Task 8.1:** Database Migration `warehouse_id` pada Tabel Orders
  - File: [`database/migrations/2026_09_21_120000_add_warehouse_id_to_orders_table.php`](file:///D:/project/motovault/database/migrations/2026_09_21_120000_add_warehouse_id_to_orders_table.php)
  - Foreign key nullable ke `warehouses(id)`.
- [x] **Task 8.2:** Model & Request Update
  - File: [`app/Models/Order.php`](file:///D:/project/motovault/app/Models/Order.php), [`app/Http/Resources/OrderResource.php`](file:///D:/project/motovault/app/Http/Resources/OrderResource.php), [`app/Http/Requests/Api/V1/PosOrderRequest.php`](file:///D:/project/motovault/app/Http/Requests/Api/V1/PosOrderRequest.php)
  - Fillable `warehouse_id`, relasi `belongsTo(Warehouse::class)`, serta validasi `warehouse_id` pada transaksi POS.
- [x] **Task 8.3:** Sinkronisasi Stok Dua Arah di `CheckoutService`
  - File: [`app/Services/CheckoutService.php`](file:///D:/project/motovault/app/Services/CheckoutService.php)
  - Potong `warehouse_stocks.stock` saat checkout (berdasarkan gudang yang dipilih / gudang pusat).
  - Pulihkan `warehouse_stocks.stock` saat pembatalan order (`cancel`).
- [x] **Task 8.4:** Automated Test untuk Sinkronisasi Multi-Warehouse
  - File: [`tests/Feature/WarehouseStockSyncTest.php`](file:///D:/project/motovault/tests/Feature/WarehouseStockSyncTest.php)
  - Uji: Checkout storefront memotong stok gudang pusat & stok global.
  - Uji: Transaksi POS memotong stok cabang gudang spesifik.
  - Uji: Pembatalan manual memulihkan stok cabang dan stok global.
  - Uji: Pembatalan kedaluwarsa otomatis memulihkan stok cabang dan stok global.

---

## Phase 9: Real-Time Notifications & WebSocket Event Streaming (Laravel Reverb)
- [x] **Task 9.1:** Konfigurasi Environment & Reverb Broadcasting
  - Update `.env` & `.env.example` dengan `BROADCAST_CONNECTION=reverb`, `REVERB_APP_KEY`, `REVERB_HOST`, dll.
- [x] **Task 9.2:** Buat Fallback API Endpoint `GET /api/v1/notifications/recent`
  - Buat `app/Http/Controllers/Api/V1/NotificationController.php`
  - Daftarkan route di `routes/api.php`
  - Kembalikan pesanan terbaru (10 terakhir) & peringatan stok menipis (`needs_restock`).
- [x] **Task 9.3:** Integrasi Frontend Reverb Client, Web Audio Synthesizer, & Toast Alert di POS Terminal
  - Update `resources/views/pos.blade.php`
  - Sisipkan Pusher JS & Laravel Echo CDN
  - Implementasikan audio chime Web Audio API (tanpa MP3 eksternal)
  - Pasang container toast alert melayang & dropdown notification center di navbar POS
  - Pasang listener WebSocket untuk event `OrderCreatedEvent`, `OrderStatusUpdatedEvent`, dan `LowStockAlertEvent`
  - Implementasikan auto-fallback ke polling API `/api/v1/notifications/recent` saat WebSocket terputus
- [x] **Task 9.4:** Integrasi Notifikasi Real-time pada Storefront
  - Update `resources/views/welcome.blade.php` dengan listener order status & notifikasi
- [x] **Task 9.5:** Automated Test Suite untuk Real-Time & Fallback Notifications
  - Buat `tests/Feature/NotificationApiTest.php`
  - Jalankan `php artisan test` dan verifikasi semua pengujian lulus.

---

## Phase 10: Ekspor Laporan Penjualan & Pergudangan (Excel / CSV / PDF)
- [x] **Task 10.1:** Service Rekapitulasi & Ekspor Laporan Penjualan
  - File: [`app/Services/Report/SalesReportService.php`](file:///D:/project/motovault/app/Services/Report/SalesReportService.php)
  - Kalkulasi omset, jumlah transaksi, total unit terjual, breakdown per metode pembayaran & gudang cabang.
  - Generator CSV terstandarisasi RFC-4180 dengan UTF-8 BOM untuk Microsoft Excel / Google Sheets.
- [x] **Task 10.2:** API & Web Endpoint Download Laporan CSV/Excel
  - File: [`app/Http/Controllers/Api/V1/Admin/SalesReportController.php`](file:///D:/project/motovault/app/Http/Controllers/Api/V1/Admin/SalesReportController.php)
  - Endpoint API: `GET /api/v1/admin/reports/sales` (ringkasan JSON) & `GET /api/v1/admin/reports/sales/export` (unduh file CSV)
  - Endpoint Web: `GET /pos/reports/sales/export`
- [x] **Task 10.3:** Tombol & UI Ekspor di POS Terminal Kasir
  - File: [`resources/views/pos.blade.php`](file:///D:/project/motovault/resources/views/pos.blade.php)
  - Tombol navbar "Laporan Penjualan", modal interaktif dengan filter tanggal, cabang gudang, dan status bayar.
  - Preview dinamis omset terpilih dan tombol download satu kali klik.
- [x] **Task 10.4:** Automated Test untuk Ekspor Laporan Penjualan
  - File: [`tests/Feature/SalesReportExportTest.php`](file:///D:/project/motovault/tests/Feature/SalesReportExportTest.php) (5 test cases, PASS 100%).


