# PRD & Technical Design: Real-Time Notifications & WebSocket Event Streaming (Laravel Reverb)

**Dokumen Versi:** 1.0.0  
**Tanggal:** 21 September 2026  
**Status:** Approved & In Implementation  
**Modul Terkait:** Kasir POS ([`pos.blade.php`](file:///D:/project/motovault/resources/views/pos.blade.php)), Storefront ([`welcome.blade.php`](file:///D:/project/motovault/resources/views/welcome.blade.php)), Broadcasting ([`routes/channels.php`](file:///D:/project/motovault/routes/channels.php)), Events ([`app/Events/`](file:///D:/project/motovault/app/Events/)).

---

## 1. Latar Belakang & Masalah
Dalam operasional bengkel dan toko suku cadang motor MotoVault, kasir fisik (*offline POS*) dan admin gudang memerlukan visibilitas instan atas transaksi online yang masuk serta penurunan stok kritis (*low stock alert*) tanpa harus me-refresh halaman berulang kali. Keterlambatan mengetahui pesanan baru dapat memperlambat proses *fulfillment* dan meningkatkan risiko *overselling*.

---

## 2. Tujuan & Sasaran (Objectives & Scope)
1. **Real-time Event Delivery**: Mengirimkan notifikasi instan (<50ms) menggunakan **Laravel Reverb** (protokol WebSocket) untuk event:
   - `OrderCreatedEvent` (Pesanan baru via Storefront / POS).
   - `OrderStatusUpdatedEvent` (Pembayaran lunas via Midtrans webhook / update fulfillment).
   - `LowStockAlertEvent` (Stok varian mencapai ambang batas `min_stock_alert`).
2. **Resilient Hybrid Fallback**: Jika daemon Reverb (`php artisan reverb:start`) sedang offline atau koneksi socket terputus, frontend secara otomatis mengaktifkan polling cerdas ke API endpoint `GET /api/v1/notifications/recent` setiap 15 detik.
3. **UI/UX Kasir Interaktif**:
   - Audio Chime instan via Web Audio API browser (dua nada harmonis tanpa file MP3 eksternal).
   - Toast popup melayang dengan tombol "Lihat Detail" dan animasi dismiss otomatis.
   - Dropdown Notification Center pada navbar POS & Storefront dengan badge unread counter.
   - Auto-insert baris pesanan baru pada tabel transaksi kasir secara reaktif.

---

## 3. Spesifikasi Arsitektur Backend

### 3.1 Konfigurasi Broadcasting & Reverb
- File: [`config/broadcasting.php`](file:///D:/project/motovault/config/broadcasting.php), [`.env`](file:///D:/project/motovault/.env), [`.env.example`](file:///D:/project/motovault/.env.example)
- Default broadcaster: `reverb`
- Parameter koneksi:
  - `REVERB_APP_ID`: `motovault-app`
  - `REVERB_APP_KEY`: `motovault-key`
  - `REVERB_APP_SECRET`: `motovault-secret`
  - `REVERB_HOST`: `127.0.0.1`
  - `REVERB_PORT`: `8080`
  - `REVERB_SCHEME`: `http`

### 3.2 Channel Siaran & Payload Kontrak
1. **Channel `orders` & `orders.staff`** (`OrderCreatedEvent`):
   ```json
   {
     "order_id": 12,
     "order_number": "MV-ORD-20260921-0001",
     "customer_name": "Budi Santoso",
     "total_amount": 185000,
     "payment_status": "unpaid",
     "fulfillment_status": "pending",
     "payment_method": "midtrans_snap",
     "items_count": 2,
     "created_at": "2026-09-21T13:00:00+07:00"
   }
   ```
2. **Channel `inventory.alerts` & `inventory.staff`** (`LowStockAlertEvent`):
   ```json
   {
     "variant_id": 5,
     "sku": "MOT-3100-08L",
     "product_name": "Oli Motul 3100 Gold 0.8L",
     "variant_name": "0.8L",
     "current_stock": 3,
     "min_stock_alert": 5,
     "alert_message": "Perhatian! Stok SKU MOT-3100-08L tersisa 3 unit (ambang batas: 5).",
     "timestamp": "2026-09-21T13:00:00+07:00"
   }
   ```

### 3.3 Fallback API Controller (`NotificationController`)
- **Route**: `GET /api/v1/notifications/recent`
- **Controller**: `App\Http\Controllers\Api\V1\NotificationController::recent`
- **Output Data Structure**:
  ```json
  {
    "success": true,
    "message": "Recent notifications retrieved",
    "data": {
      "recent_orders": [ ... ],
      "low_stock_alerts": [ ... ],
      "unread_count": 3
    },
    "errors": null
  }
  ```

---

## 4. Spesifikasi Arsitektur Frontend

### 4.1 Client Loader (Laravel Echo + Pusher JS)
- Memuat CDN `pusher.min.js` dan `echo.iife.js`.
- Inisialisasi otomatis menggunakan konfigurasi yang dioper dari backend environment.

### 4.2 Web Audio API Synthesizer
- Class/Object `NotificationSound`:
  - `playOrderChime()`: Membunyikan nada D5 (587.33 Hz) dan A5 (880 Hz) secara berurutan dengan gain envelope lembut.
  - `playAlertChime()`: Membunyikan nada peringatan E4 (329.63 Hz).
  - Dilengkapi flag `isMuted` yang disimpan di `localStorage`.

### 4.3 UI Notification System
- **State Alpine.js / Vanilla State Manager**:
  - Menyimpan array `notifications: []`, status koneksi `connectionStatus: 'connecting' | 'connected' | 'fallback'`.
  - Mengelola toast container dengan z-index tinggi di pojok kanan layar.
  - Dropdown panel notifikasi di header navbar dengan tombol "Tandai Dibaca".

---

## 5. Rencana Pengujian Otomatis (Automated Tests)
1. `tests/Feature/NotificationApiTest.php`:
   - Pengujian pemanggilan `GET /api/v1/notifications/recent` menghasilkan pesanan terbaru dan alert stok menipis.
   - Pengujian otorisasi dan response envelope standar `ApiResponse::success`.
2. `tests/Feature/BroadcastingEventsTest.php`:
   - Memastikan semua event broadcast (`OrderCreatedEvent`, `OrderStatusUpdatedEvent`, `LowStockAlertEvent`) tetap lulus tanpa degradasi.
3. Regresi penuh seluruh test suite (`php artisan test`).
