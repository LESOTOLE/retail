# Design Specification: MotoVault Modern Storefront & Online Shopping Flow (Bagian 1)

- **Date:** 2026-09-18
- **Author:** Antigravity & User
- **Status:** Approved / Ready for Implementation Planning
- **Scope:** Storefront UI/UX, Reactive Alpine.js Cart, Slide-Over Cart Drawer, 3-Step Checkout Modal with Live 3PL Shipping, Customer Order Tracking, My Garage Compatibility Filter, and Frictionless Customer Authentication.

---

## 1. Executive Summary & Problem Statement

### 1.1 Background
MotoVault telah berhasil membangun pondasi arsitektur enterprise pada backend (AI Chat Streaming SSE, RAG Semantic Search, 3PL Multi-Ekspedisi Logistics, WebSockets via Laravel Reverb, Multi-Warehouse Proximity Routing, dan Dedicated POS Terminal). Namun, antarmuka depan (*Storefront*) pada `resources/views/welcome.blade.php` sebelumnya masih berupa landing page katalog statis tanpa pengalaman belanja modern:
- Tidak memiliki keranjang belanja (*shopping cart*) interaktif dan reaktif.
- Belum terhubung ke endpoint ongkir 3PL real-time (JNE, J&T, SiCepat).
- Tidak ada fitur seleksi motor pelanggan (*My Garage*) untuk memfilter sparepart yang kompatibel secara otomatis.
- Tidak ada drawer pelacakan pesanan (*Order Tracking*) bagi customer.
- Form checkout belum menyediakan alur multi-step terstruktur dengan pilihan metode pembayaran (QRIS, VA, Transfer Bank) dan pilihan kurir.

### 1.2 Objective & Success Metrics
1. **Industry-Grade UX:** Menghadirkan antarmuka e-commerce sparepart otomotif modern setara standar industri (Tokopedia, Blibli, RevZilla).
2. **Zero-Dropoff Checkout:** Pengalaman checkout 3 langkah yang mulus dengan auto-register/login customer langsung di modal tanpa redirect.
3. **Real-Time Integration:** Keranjang reaktif divalidasi ke backend (`/api/v1/cart/validate`), kalkulasi ongkir live 3PL (`/api/v1/shipping/rates`), dan eksekusi transaksi atomik (`/api/v1/orders/checkout`).
4. **My Garage Personalization:** Filter kompatibilitas motor pelanggan yang menempel pada header/hero dan memberi lencana hijau (*"Pasti Pas untuk [Motor Anda]"*) pada kartu katalog produk.
5. **No Build Pipeline Friction:** Memanfaatkan CDN Alpine.js + Tailwind CSS yang sudah ada, tanpa mewajibkan kompilasi frontend yang rumit, dengan persistensi `localStorage`.

---

## 2. Architecture & State Management

### 2.1 State Architecture (Alpine.js Stores)
Seluruh status interaktif storefront dikelola oleh Alpine.js Stores terpusat dengan sinkronisasi ke `localStorage`:

```
+-------------------------------------------------------------+
|                     Alpine.js Root Store                    |
+------------------------------+------------------------------+
                               |
       +-----------------------+-----------------------+
       |                       |                       |
+------v------+         +------v------+         +------v------+
| Alpine.store|         | Alpine.store|         | Alpine.store|
|   ('cart')  |         |  ('garage') |         |   ('auth')  |
+------+------+         +------+------+         +------+------+
       |                       |                       |
       v                       v                       v
- items[] (SKU, qty,    - selectedVehicle:      - token (Sanctum)
  name, price, image)     { id, brand, model,   - user: { id,
- subtotal, count         year }                  name, email }
- isDrawerOpen          - isFilterActive        - isAuthenticated
- checkoutStep          - compatibleSkus[]      - login(), logout()
- shippingRate, courier
- localStorage Sync     - localStorage Sync     - localStorage Sync
```

### 2.2 LocalStorage Keys & Data Structure
- `motovault_cart`: JSON array berisi `[{ sku, name, price, quantity, image_url, stock, weight_gram }]`.
- `motovault_garage`: JSON object berisi `{ id, brand, model, year, name }`.
- `motovault_token`: Sanctum PlainText Bearer Token string.
- `motovault_user`: JSON object berisi data akun customer `{ id, name, email, phone }`.
- `motovault_recent_orders`: JSON array berisi riwayat nomor pesanan terakhir untuk pelacakan instan di drawer.

---

## 3. UI Components & User Experience Flow

### 3.1 Header & Navigation Enhancements
- **Sticky Navbar:** Tetap melayang di bagian atas dengan logo MotoVault, bilah pencarian cerdas, tombol *"Garasi Saya"*, indikator login pelanggan (*"Masuk / Daftar"* atau avatar nama), dan tombol Keranjang Belanja dengan badge counter merah dinamis.
- **My Garage Filter Bar:** Dropdown pilih Pabrikan (Honda, Yamaha, Kawasaki, Suzuki) -> Model (Vario 160, NMAX 155, Ninja 250, dll) -> Tahun. Saat dipilih, katalog produk secara otomatis memfilter produk yang kompatibel dan memberi lencana *"Pasti Cocok untuk Motor Anda"* dengan centang hijau.
- **Floating Cart Pill:** Tombol mengambang di pojok kanan bawah dengan total item dan subtotal harga untuk akses 1-klik ke keranjang belanja dari mana pun.

### 3.2 Slide-Over Cart Drawer
- **Trigger:** Klik tombol keranjang di navbar, floating pill, atau tombol *"Beli Sekarang"* di kartu produk.
- **Isi Drawer:**
  - Header dengan jumlah item dan tombol tutup (`x`).
  - Daftar item keranjang: Thumbnail gambar, nama produk, varian/SKU, harga satuan, dan kontrol kuantitas (`-` / input / `+`) serta tombol hapus.
  - Peringatan stok jika kuantitas mendekati batas stok gudang.
  - Ringkasan subtotal harga.
  - Tombol aksi utama: *"Lanjut ke Checkout"* (memicu Checkout Modal).
  - Empty State: Ilustrasi keranjang kosong yang rapi dengan tombol CTA *"Mulai Belanja"*.

### 3.3 3-Step Checkout Modal
Modal layar penuh/pop-up terpusat dengan stepper indikator (Langkah 1 -> Langkah 2 -> Langkah 3):

```
+-------------------------------------------------------------+
|               CHECKOUT MOTOVAULT (Step 1 of 3)              |
+-------------------------------------------------------------+
|  [ 1. Data & Alamat ] ----> ( 2. Ekspedisi ) ----> ( 3. Bayar )
+-------------------------------------------------------------+
| Nama Lengkap:     [ Septian Pratama                  ]      |
| No. Handphone:    [ 081234567890                     ]      |
| Email:            [ septian@example.com              ]      |
| Kota / Kode Pos:  [ Jakarta Selatan / 12340          ]      |
| Alamat Lengkap:   [ Jl. Senopati No. 45, Kebayoran Baru]   |
+-------------------------------------------------------------+
|                                  [ Batal ] [ Lanjut: Kurir >]
+-------------------------------------------------------------+
```

1. **Step 1 — Kontak & Alamat Pengiriman:**
   - Input: Nama, No. HP, Email, Alamat Lengkap, dan Kode Pos 5 digit.
   - Jika pengguna belum login, form menyediakan opsi instan untuk auto-register tanpa harus meninggalkan form checkout.
2. **Step 2 — Pilihan Ekspedisi 3PL & Kalkulasi Ongkir:**
   - Secara dinamis memanggil `POST /api/v1/shipping/rates` menggunakan kode pos tujuan dan total berat item.
   - Menampilkan pilihan kurir:
     - **JNE** (REG Rp 18.000, 2-3 hari | YES Rp 32.000, 1 hari)
     - **J&T** (EZ Rp 19.000, 2-3 hari)
     - **SiCepat** (BEST Rp 20.000, 1-2 hari)
     - **GoSend / Instant** (Bila dalam area jangkauan gudang terdekat)
   - Memilih kurir secara otomatis memperbarui rincian total belanja: `Total = Subtotal Produk + Ongkos Kirim`.
3. **Step 3 — Metode Pembayaran & Review Pesanan:**
   - Ringkasan akhir: Item belanja + Ongkir + Total Pembayaran.
   - Pilihan metode pembayaran:
     - QRIS (BCA, Mandiri, GoPay, OVO, ShopeePay)
     - Virtual Account (BCA VA, BRI VA, Mandiri VA)
     - Transfer Bank Manual
   - Tombol *"Bayar Sekarang"*: Mengunci stok secara atomik melalui `POST /api/v1/orders/checkout`.

### 3.4 Layar Sukses & Order Tracking Drawer
- Setelah checkout sukses:
  - Keranjang belanja dikosongkan.
  - Nomor pesanan (misal: `ORD-20260918-XXXX`) disimpan ke `localStorage`.
  - Ditampilkan Modal Sukses dengan instruksi pembayaran (nomor VA / QRIS mock) dan tautan *"Lacak Pesanan Saya"*.
- **Drawer "Pesanan Saya":**
  - Mengambil riwayat pesanan pelanggan dari `GET /api/v1/orders`.
  - Menampilkan status pembayaran (Unpaid, Paid, Failed) dan status pengiriman (Pending, Processing, Shipped, Delivered).
  - Jika pesanan memiliki nomor resi ekspedisi, customer dapat melihat timeline pelacakan live langsung via `GET /api/v1/shipping/track/{waybill_number}`.

---

## 4. Backend API Integration & Contracts

### 4.1 Endpoints Specification

| Endpoint | Method | Middleware | Kegunaan |
| :--- | :---: | :---: | :--- |
| `/api/v1/cart/validate` | `POST` | Guest | Validasi ketersediaan stok aktual dan kalkulasi subtotal harga sebelum membuka checkout. |
| `/api/v1/shipping/rates` | `POST` | Guest | Menghitung ongkos kirim multi-kurir berdasarkan `destination_postal_code` dan bobot barang. |
| `/api/v1/orders/checkout` | `POST` | `auth:sanctum` | Membuat invoice pesanan, mengunci stok di database, menyimpan informasi alamat dan kurir, serta memicu event WebSockets. |
| `/api/v1/orders` | `GET` | `auth:sanctum` | Mengambil daftar pesanan milik pelanggan terautentikasi. |
| `/api/v1/orders/{order_number}` | `GET` | `auth:sanctum` | Mengambil detail spesifik satu pesanan beserta item dan status pembayaran. |
| `/api/v1/shipping/track/{waybill}` | `GET` | Guest | Mengambil riwayat perjalanan paket pengiriman ekspedisi 3PL secara real-time. |
| `/api/v1/auth/login` & `/register` | `POST` | Guest | Autentikasi pelanggan untuk mendapatkan Sanctum Bearer Token. |

### 4.2 Data Payload Details

#### POST /api/v1/shipping/rates
```json
{
  "destination_postal_code": "12340",
  "items": [
    {
      "variant_id": 1,
      "quantity": 2,
      "weight_gram": 500
    }
  ]
}
```

#### POST /api/v1/orders/checkout (Extended Payload)
```json
{
  "items": [
    { "sku": "BRK-PAD-BMB-01", "quantity": 1 }
  ],
  "shipping_address": "Jl. Senopati No. 45, Jakarta Selatan (12340)",
  "courier_code": "jne",
  "courier_service": "REG",
  "shipping_cost": 18000,
  "payment_method": "qris"
}
```

---

## 5. Security, Reliability & Edge Cases

1. **Stok Habis / Rebutan Stok (Race Conditions):**
   - Backend menggunakan `DB::transaction` dengan pessimistic locking (`SELECT ... FOR UPDATE`).
   - Jika stok tidak mencukupi saat checkout, sistem melempar `InsufficientStockException` (HTTP 409). Frontend menangkap ini dan menampilkan pesan ramah serta memperbarui kuantitas keranjang secara otomatis.
2. **Idempotency & Mencegah Pembayaran Ganda:**
   - Tombol submit checkout memiliki status loading dan disabled seketika tombol diklik.
   - Order Number di-generate secara unik dengan format berbasis waktu dan random generator.
3. **Session Persistence:**
   - Seluruh data keranjang dan garasi motor pelanggan aman tersimpan di browser via `localStorage`, tidak akan hilang meskipun tab ditutup atau di-refresh.
4. **Offline / Network Fault Tolerance:**
   - Fallback tarif estimasi bila koneksi ke API ongkir mengalami kegagalan sementara.

---

## 6. Testing & Quality Assurance Plan

1. **Automated Feature Tests (`tests/Feature/StorefrontCheckoutTest.php`):**
   - Tes validasi keranjang belanja (`POST /api/v1/cart/validate`).
   - Tes kalkulasi tarif ongkir untuk item keranjang (`POST /api/v1/shipping/rates`).
   - Tes transaksi checkout lengkap dengan alamat pengiriman dan ekspedisi.
   - Tes penolakan transaksi jika stok tidak mencukupi (HTTP 409).
   - Memastikan seluruh 50 tes enterprise yang sudah ada tetap 100% lolos (*green*).
2. **Interactive UI Verification:**
   - Verifikasi penambahan barang ke keranjang dari kartu produk.
   - Verifikasi slide-over cart drawer buka/tutup dan kontrol kuantitas.
   - Verifikasi modal checkout 3-langkah dan kalkulasi ongkir live.
   - Verifikasi filter garasi motor ("My Garage") pada katalog produk.
   - Verifikasi responsivitas pada tampilan desktop dan mobile.
