# 🏍️ MotoVault — Enterprise Smart Automotive Parts & Omnichannel Platform

[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![Realtime Reverb](https://img.shields.io/badge/Broadcasting-Laravel_Reverb-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/docs/reverb)
[![Gemini AI](https://img.shields.io/badge/AI_Engine-Google_Gemini_2.0-4285F4?style=for-the-badge&logo=google&logoColor=white)](https://ai.google.dev/)
[![Test Suite](https://img.shields.io/badge/Tests-80_Passed_|_658_Assertions-22c55e?style=for-the-badge&logo=checkmarx&logoColor=white)](tests)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](LICENSE)

> **MotoVault** adalah platform *e-commerce*, manajemen inventaris multi-gudang, dan kasir toko fisik (*Point of Sale* / POS) terpadu khusus industri otomotif (*spare parts* & aksesoris). Dilengkapi mesin pencocokan kompatibilitas kendaraan deterministik (*Year-Make-Model*), asisten konsultatif AI berbasis Gemini *Tool Calling* & *Diagnostic RAG*, serta arsitektur *event-driven* real-time berbasis Laravel Reverb.

---

## 📌 Daftar Isi
- [Latar Belakang & Solusi](#-latar-belakang--solusi)
- [Fitur Utama](#-fitur-utama)
- [Arsitektur Sistem](#-arsitektur-sistem)
- [Tech Stack](#-tech-stack)
- [Struktur Proyek](#-struktur-proyek)
- [Panduan Instalasi & Menjalankan](#-panduan-instalasi--menjalankan)
- [Akun Demo & Akses Cepat](#-akun-demo--akses-cepat)
- [Dokumentasi API](#-dokumentasi-api)
- [Pengujian Otomatis (Automated Tests)](#-pengujian-otomatis-automated-tests)
- [Lisensi](#-lisensi)

---

## 💡 Latar Belakang & Solusi

### Masalah Industri Otomotif
Membeli suku cadang (*spare parts*), oli, aki, kampas rem, hingga aksesoris motor sering menimbulkan risiko salah beli akibat ketidakcocokan teknis (*compatibility mismatch*). Akibatnya:
- Angka pengembalian barang (*return rate*) suku cadang e-commerce mencapai 12%–18%.
- Ketidaksinkronan stok antara toko fisik dan platform online (*race condition* / overselling).
- Konsumen kesulitan mendiagnosa keluhan motor secara mandiri tanpa pergi ke bengkel.

### Solusi MotoVault
1. **Deterministic Compatibility Engine:** Relasi matriks *Year-Brand-Model-Trim* memastikan setiap suku cadang yang dibeli 100% cocok dengan motor konsumen.
2. **AI Consultative Assistant (Zero Hallucination):** Agen AI Gemini dengan *Tool Calling* langsung mengecek basis data katalog dan stok nyata sebelum memberi rekomendasi.
3. **Omnichannel & Multi-Warehouse POS:** Transaksi toko offline (POS kasir) dan online tersinkronisasi dua arah secara instan, dilengkapi algoritma *Haversine Proximity Routing* untuk pemenuhan pesanan dari gudang terdekat.
4. **Auto-Release Stock Leak Prevention:** Pesanan *unpaid* otomatis dibatalkan via scheduler dalam 30 menit (TTL), memulihkan kuota stok ke gudang secara presisi.

---

## ✨ Fitur Utama

### 🛒 1. Storefront E-Commerce Cerdas
- **Vehicle Garage Filter:** Saring katalog otomatis sesuai merek, model, dan tahun motor pengguna.
- **Multi-Variant Products:** Dukungan varian spesifikasi (ukuran, kapasitas daya, warna, tipe).
- **3PL Logistics Shipping Rates:** Perhitungan ongkos kirim multi-kurir (JNE, J&T, SiCepat, dll.) berdasarkan berat dan jarak.
- **Midtrans Snap Payment:** Modal pembayaran terintegrasi (QRIS, GoPay, Virtual Account, Kartu Kredit).
- **Public Waybill Tracking:** Pelacakan status pengiriman publik dengan timeline milestone kurir.

### 🤖 2. Gemini AI Consultative Assistant & Diagnostic RAG
- **Tool Calling Execution:** Eksekusi fungsi internal (`search_products`, `check_compatibility`, `get_variant_stock`, `diagnose_symptom`).
- **Diagnostic RAG:** Pencocokan keluhan motor (suara decit, brebet, aki tekor) dengan suku cadang relevan via kemiripan vektor *Cosine Similarity*.
- **Streaming Response:** Dukungan Server-Sent Events (SSE) `/api/v1/ai/chat/stream` untuk interaksi percakapan *typewriter-feel*.

### 🏬 3. Point of Sale (POS) Kasir & Toko Fisik
- **Antarmuka Kasir Cepat:** Pencarian SKU/Barcode instan, filter kategori, dan penanganan keranjang kasir.
- **Direct Bill Checkout:** Transaksi langsung menggunakan Tunai, QRIS toko, maupun Transfer Bank.
- **Dukungan Multi-Cabang Gudang:** Pemotongan stok langsung pada gudang fisik tempat kasir beroperasi.
- **Web Audio API Feedback:** Audio synthesizer nada transaksi sukses dan notifikasi tanpa ketergantungan berkas audio eksternal.

### 🏭 4. Manajemen Multi-Warehouse & Logistik
- **Proximity Routing (Haversine Formula):** Rekomendasi rute pengiriman otomatis dari gudang terdekat yang memiliki stok mencukupi.
- **Inter-Warehouse Stock Transfer:** Alur transfer stok antar cabang (*Requested* → *Dispatched* → *Received* / *Cancelled*).
- **Dual-Source Stock Sync:** Sinkronisasi konsisten antara tabel `product_variants.stock` dan `warehouse_stocks.stock`.

### ⚡ 5. Real-Time WebSocket Broadcasting (Laravel Reverb)
- **Live Notifications:** Alert instan pada POS dan Storefront ketika ada pesanan baru, perubahan status pembayaran, atau stok menipis (*low stock warning*).
- **Graceful Fallback:** Otomatis beralih ke REST polling interval jika koneksi WebSocket terganggu.

### 📊 6. Laporan & Keamanan Enterprise
- **Export Laporan Penjualan:** Unduh rekap transaksi format CSV standar RFC-4180 dengan UTF-8 BOM (langsung terbaca rapi di Microsoft Excel & Google Sheets).
- **Named Rate Limiting:** Proteksi DDoS dan brute-force pada endpoint kritis (`ai-chat`, `auth`, `checkout`, `catalog`, `webhook`).
- **SHA-512 Digital Signature Verification:** Validasi ketat webhook Midtrans & token callback Xendit dengan idempotensi transaksi.

---

## 🏛️ Arsitektur Sistem

```mermaid
flowchart TD
    subgraph Client Layer
        A1["Storefront Web (Blade + Tailwind CSS v4)"]
        A2["POS Terminal Kasir (/pos)"]
        A3["Admin Console (/admin)"]
    end

    subgraph Security & Gateway
        B1["Laravel 12 API Gateway"]
        B2["Named Rate Limiters (Throttling)"]
        B3["Sanctum Token & Spatie RBAC Matrix"]
    end

    subgraph Application Services
        C1["Compatibility Engine (Year-Brand-Model)"]
        C2["Gemini AI Tool Calling & Diagnostic RAG"]
        C3["Omnichannel Checkout & Stock Guard"]
        C4["Multi-Warehouse Proximity Engine (Haversine)"]
        C5["Midtrans & Xendit Payment Gateway (SHA-512)"]
    end

    subgraph Data & Real-time Layer
        D1[("MySQL 8.0+ Primary DB")]
        D2[("Redis Cache & Session")]
        D3["Laravel Reverb (WebSockets 8080)"]
    end

    Client Layer --> B1
    B1 --> B2 --> B3
    B3 --> Application Services
    Application Services --> D1
    Application Services --> D2
    Application Services -->|Broadcast Events| D3
    D3 -.->|Live Push Alerts| Client Layer
```

---

## 🛠️ Tech Stack

| Komponen | Teknologi | Keterangan |
|---|---|---|
| **Backend Framework** | Laravel 12.x | PHP 8.2+ dengan struktur RESTful & Service Layer |
| **Frontend & UI** | Tailwind CSS v4, Vite 7, Blade | Desain modern, responsif, dark/light accents |
| **Realtime Engine** | Laravel Reverb | High-performance WebSocket broadcasting |
| **AI / LLM** | Google Gemini 2.0 Flash / OpenAI | Function calling multi-turn & Diagnostic RAG |
| **Database** | MySQL 8.0+ / SQLite | Relasi multi-gudang, pivot kompatibilitas, index transaksi |
| **Cache & Queue** | Redis 7.0+ | Manajemen antrean background job & cache layer |
| **Payment Gateway** | Midtrans Snap & Xendit | SHA-512 signature hash verification & idempotent webhook |
| **Autentikasi & RBAC**| Laravel Sanctum & Spatie Permission | Multi-role: Admin, Staff, Customer |
| **Testing** | PHPUnit 11.5 / Laravel Testing Suite | 80 Feature & Unit Tests (100% Pass) |

---

## 📂 Struktur Proyek

```text
motovault/
├── app/
│   ├── Console/Commands/       # Artisan commands (orders:cancel-expired, dll.)
│   ├── Enums/                  # Enum PHP (UserRole, OrderStatus, PaymentStatus, dll.)
│   ├── Events/                 # Event broadcast (OrderCreatedEvent, LowStockAlertEvent)
│   ├── Http/
│   │   ├── Controllers/Api/V1/ # REST API Controllers (Storefront, POS, Admin, AI)
│   │   ├── Middleware/         # Custom middleware & throttle handlers
│   │   └── Requests/Api/V1/    # Form requests & validation rules
│   ├── Models/                 # Eloquent models (Product, Vehicle, Warehouse, Order)
│   ├── Providers/              # Service providers & named rate limiters
│   └── Services/               # Domain Business Logic
│       ├── Ai/                 # Gemini Tool Calling, Embeddings, Diagnostic RAG
│       ├── Payment/            # Midtrans Snap & Xendit payment service
│       ├── Report/             # Sales report generator & CSV exporter
│       ├── Shipping/           # 3PL logistics & waybill generator
│       └── Warehouse/          # Proximity routing & stock transfer manager
├── config/                     # Konfigurasi layanan (services, reverb, auth)
├── database/
│   ├── migrations/             # Migrasi skema database terstruktur
│   └── seeders/                # Seeder RBAC, pengguna demo, kendaraan & produk
├── resources/
│   ├── js/                     # Asset JS frontend & Laravel Echo
│   ├── css/                    # Tailwind CSS v4 styling
│   └── views/
│       ├── welcome.blade.php   # Storefront E-Commerce & Gemini AI Chat
│       ├── pos.blade.php       # In-Store Point of Sale (POS) Kasir
│       └── admin/index.blade.php # Web Admin Management Console
├── routes/
│   ├── api.php                 # Rute REST API v1
│   ├── web.php                 # Rute halaman web (Storefront, POS, Admin)
│   └── console.php             # Penjadwalan task Laravel Scheduler
└── tests/                      # 80 Automated Feature & Unit Test Suites
```

---

## 🚀 Panduan Instalasi & Menjalankan

### 1. Prasyarat Sistem
- PHP >= 8.2 (dengan ekstensi `pdo`, `mbstring`, `openssl`, `bcmath`, `curl`)
- Composer >= 2.5
- Node.js >= 18.x & NPM
- MySQL 8.0+ atau PostgreSQL / SQLite
- Redis (opsional untuk local, direkomendasikan untuk production)

### 2. Kloning Repositori
```bash
git clone https://github.com/LESOTOLE/retail.git
cd retail
```

### 3. Instal Dependensi PHP & JavaScript
```bash
composer install
npm install
```

### 4. Konfigurasi Environment File
Salin file template `.env.example` ke `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

Sesuaikan parameter database dan kunci layanan di file `.env`:
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=motovault
DB_USERNAME=root
DB_PASSWORD=

# Gemini AI API (Dapatkan dari Google AI Studio)
AI_DRIVER=gemini
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_MODEL=gemini-2.0-flash

# Midtrans Snap (Sandbox / Production)
PAYMENT_PROVIDER=midtrans
MIDTRANS_SERVER_KEY=your_midtrans_server_key
MIDTRANS_CLIENT_KEY=your_midtrans_client_key
MIDTRANS_IS_PRODUCTION=false

# Laravel Reverb (WebSocket)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=motovault-app
REVERB_APP_KEY=motovault-key
REVERB_APP_SECRET=motovault-secret
REVERB_HOST="127.0.0.1"
REVERB_PORT=8080
REVERB_SCHEME=http
```

### 5. Jalankan Migrasi & Seeder Database
```bash
php artisan migrate --seed
```

### 6. Build Aset Frontend
```bash
# Untuk pengembangan (Vite dev server)
npm run dev

# Atau build untuk mode produksi
npm run build
```

### 7. Menjalankan Server & Background Worker
Buka terminal dan jalankan server aplikasi:
```bash
php artisan serve
```

Di terminal terpisah, jalankan WebSocket server (Laravel Reverb) dan antrean queue:
```bash
# Jalankan WebSocket server
php artisan reverb:start

# Jalankan Queue worker
php artisan queue:work

# (Opsional) Jalankan Scheduler untuk auto-cancel pesanan kadaluwarsa
php artisan schedule:work
```

---

## 🔑 Akun Demo & Akses Cepat

Setelah menjalankan `php artisan db:seed`, akun demo berikut siap digunakan:

| Persona / Role | Email | Password | Akses URL | Fitur Utama |
|---|---|---|---|---|
| **Super Admin** | `admin@motovault.test` | `password` | `/admin` | Manajemen Produk, Matriks Kompatibilitas, Gudang, Log AI |
| **Kasir (Staff)** | `staff@motovault.test` | `password` | `/pos` | Kasir Cepat, Direct Bill, Alert Stok Menipis, Ekspor Laporan |
| **Customer** | `customer@motovault.test` | `password` | `/` | Belanja Suku Cadang, Chat Konsultasi AI, Checkout Snap |

---

## 📡 Dokumentasi API

Seluruh endpoint REST API menggunakan prefix `/api/v1` dan mengembalikan respon berformat JSON terstandarisasi (`ApiResponse`).

### Endpoint Publik & Pelanggan
- `GET /api/v1/products` : Katalog produk dengan filter kendaraan (`vehicle_id`) & kategori.
- `GET /api/v1/products/{slug}` : Detail produk lengkap dengan varian dan kompatibilitas motor.
- `GET /api/v1/vehicles` : Master data merek, model, dan tahun kendaraan.
- `POST /api/v1/cart/validate` : Validasi ketersediaan stok keranjang belanja.
- `POST /api/v1/shipping/rates` : Kalkulasi tarif pengiriman multi-kurir.
- `POST /api/v1/orders/checkout` : Buat pesanan baru dan dapatkan token Midtrans Snap.
- `GET /api/v1/shipping/track/{waybill}` : Cek status resi pengiriman publik.
- `POST /api/v1/ai/chat` : Konsultasi asisten AI dengan Gemini Tool Calling.
- `POST /api/v1/ai/chat/stream` : Streaming SSE percakapan AI interaktif.

### Endpoint Kasir & POS
- `POST /api/v1/pos/orders` : Transaksi kasir langsung (Tunai / QRIS / Transfer) dengan pemotongan stok gudang cabang.
- `GET /api/v1/notifications/recent` : Ambil notifikasi pesanan terbaru & stok kritis.

### Endpoint Admin & Manajemen
- `POST /api/v1/admin/products` : Tambah produk & varian baru.
- `POST /api/v1/admin/products/{id}/compatibility` : Sinkronisasi matriks kompatibilitas kendaraan.
- `GET /api/v1/admin/warehouses/transfers` : Monitoring transfer stok antar gudang.
- `GET /api/v1/admin/reports/sales` : Ringkasan omset dan analitik penjualan.
- `GET /api/v1/admin/reports/sales/export` : Unduh berkas rekap penjualan CSV (RFC-4180).

### Webhook
- `POST /api/v1/webhooks/payment` : Penanganan notifikasi pembayaran otomatis dari payment gateway dengan verifikasi SHA-512.

---

## 🧪 Pengujian Otomatis (Automated Tests)

MotoVault dilengkapi dengan rangkaian pengujian komprehensif (Unit & Feature Tests) untuk menjamin integritas logika bisnis, keamanan webhook, kalkulasi proximity, dan alur transaksi end-to-end.

Jalankan test suite dengan perintah:
```bash
php artisan test
```

### Hasil Verifikasi:
```text
  Tests:    80 passed (658 assertions)
  Duration: ~65s
```
Daftar pengujian mencakup:
- ✅ **MidtransPaymentAndWebhookSecurityTest**: Validasi SHA-512, penolakan signature palsu, idempotensi webhook.
- ✅ **MultiWarehouseProximityTest**: Haversine distance sorting, transfer stok antar gudang, pembatalan transfer.
- ✅ **CancelExpiredOrdersTest**: Verifikasi pengembalian stok global dan gudang cabang setelah 30 menit.
- ✅ **AiChatApiTest & DiagnosticRagTest**: Eksekusi Tool Calling Gemini, fallback lokal, cosine similarity RAG.
- ✅ **RateLimiterApiTest**: Proteksi throttling HTTP 429 pada endpoint AI dan autentikasi.
- ✅ **SalesReportExportTest**: Validasi struktur CSV UTF-8 BOM dan hak akses peran.
- ✅ **StorefrontEndToEndFlowTest**: Alur belanja lengkap mulai dari pemilihan motor, keranjang, ongkir, hingga pembuatan order.

---

## 📄 Lisensi

Proyek ini dilisensikan di bawah lisensi terbuka [MIT License](LICENSE). Bebas digunakan, dimodifikasi, dan dikembangkan untuk keperluan komersial maupun non-komersial.
