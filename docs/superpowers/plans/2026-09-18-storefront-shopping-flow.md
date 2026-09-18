# MotoVault Storefront Shopping Flow (Bagian 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform MotoVault's storefront (`resources/views/welcome.blade.php`) into a complete, industry-standard automotive e-commerce experience featuring an Alpine.js reactive cart, Slide-Over Cart Drawer, 3-Step Checkout Modal with live 3PL shipping rates, "My Garage" motorcycle compatibility filter, and an Order Tracking Drawer.

**Architecture:** Frontend state is governed by Alpine.js stores (`cart`, `garage`, `auth`) synchronized with `localStorage` for offline persistence and zero compilation overhead. The backend extends the atomic checkout pipeline (`CheckoutService`) to optionally accept shipping details and link directly to 3PL logistics (`ShippingOrder`), dispatching real-time WebSockets events upon order placement.

**Tech Stack:** Laravel 11, PHP 8.2+, Alpine.js 3.x (CDN), Tailwind CSS (CDN), SQLite / MySQL, Laravel Sanctum, Laravel Reverb.

## Global Constraints

- Preserve all existing 50 passing automated tests across core features without regressions.
- No Node/NPM build pipeline required — Alpine.js and Tailwind are loaded via CDN in Blade.
- Atomic database transactions with pessimistic row locking (`lockForUpdate`) for inventory deduction.
- 100% backward compatibility on `/api/v1/orders/checkout` (existing tests omit shipping fields and must continue to pass).

---

### Task 1: Backend Extended Checkout & Shipping Integration

**Files:**
- Modify: `app/Http/Requests/Api/V1/CartValidateRequest.php:20-40`
- Modify: `app/Services/CheckoutService.php:25-75`
- Modify: `app/Http/Controllers/Api/V1/OrderController.php:28-45`
- Test: `tests/Feature/StorefrontCheckoutTest.php`

**Interfaces:**
- Consumes: `App\Models\Order`, `App\Models\ShippingOrder`, `App\Services\InventoryService`
- Produces: Extended `POST /api/v1/orders/checkout` accepting `shipping_address`, `courier_code`, `courier_service`, `shipping_cost`, `payment_method`, `destination_postal_code`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/StorefrontCheckoutTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingOrder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_checkout_with_shipping_details_creates_order_and_shipping_order(): void
    {
        $user = User::factory()->create(['role' => UserRole::Customer]);
        $user->assignRole(UserRole::Customer->value);

        $category = Category::create(['name' => 'Pengereman', 'slug' => 'pengereman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Kampas Rem Brembo',
            'slug' => 'kampas-rem-brembo',
            'brand' => 'Brembo',
            'base_price' => 150000,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'BRK-BREMBO-01',
            'variant_name' => 'Front Standard',
            'additional_price' => 25000,
            'stock' => 10,
        ]);

        $payload = [
            'items' => [
                ['sku' => 'BRK-BREMBO-01', 'quantity' => 2],
            ],
            'shipping_address' => 'Jl. Sudirman No. 10, Jakarta Selatan',
            'destination_postal_code' => '12190',
            'courier_code' => 'jne',
            'courier_service' => 'REG',
            'shipping_cost' => 18000,
            'payment_method' => 'qris',
        ];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/orders/checkout', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        // Subtotal = (150000 + 25000) * 2 = 350000. Total = 350000 + 18000 = 368000
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total_amount' => 368000.00,
            'payment_method' => 'qris',
            'shipping_address' => 'Jl. Sudirman No. 10, Jakarta Selatan',
        ]);

        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order);

        $this->assertDatabaseHas('shipping_orders', [
            'order_id' => $order->id,
            'courier_code' => 'jne',
            'courier_service' => 'REG',
            'shipping_cost' => 18000.00,
            'destination_postal_code' => '12190',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StorefrontCheckoutTest`
Expected: FAIL (total_amount does not include shipping_cost and shipping_orders record is not created).

- [ ] **Step 3: Implement minimal backend code**

Modify `app/Http/Requests/Api/V1/CartValidateRequest.php`:
Add optional rules for shipping:
```php
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.sku' => ['required', 'string', 'exists:product_variants,sku'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'destination_postal_code' => ['nullable', 'string', 'max:10'],
            'courier_code' => ['nullable', 'string', 'max:50'],
            'courier_service' => ['nullable', 'string', 'max:50'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
        ];
```

Modify `app/Services/CheckoutService.php`:
Extend `checkout` method signature and logic:
```php
    public function checkout(User $user, array $items, array $shippingData = []): Order
    {
        return DB::transaction(function () use ($user, $items, $shippingData): Order {
            $result = $this->inventory->validateCart($items, lock: true);

            if (! $result['valid']) {
                throw new InsufficientStockException($result['issues']);
            }

            $shippingCost = (float) ($shippingData['shipping_cost'] ?? 0);
            $totalAmount = (float) $result['total_amount'] + $shippingCost;

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => $user->id,
                'total_amount' => $totalAmount,
                'payment_status' => PaymentStatus::Unpaid,
                'fulfillment_status' => FulfillmentStatus::Pending,
                'payment_method' => $shippingData['payment_method'] ?? null,
                'shipping_address' => $shippingData['shipping_address'] ?? null,
            ]);

            if (! empty($shippingData['courier_code'])) {
                \App\Models\ShippingOrder::create([
                    'order_id' => $order->id,
                    'courier_code' => $shippingData['courier_code'],
                    'courier_service' => $shippingData['courier_service'] ?? 'REG',
                    'shipping_cost' => $shippingCost,
                    'origin_postal_code' => \App\Services\Shipping\ShippingRateService::DEFAULT_ORIGIN_POSTAL_CODE,
                    'destination_postal_code' => $shippingData['destination_postal_code'] ?? '00000',
                    'destination_address' => $shippingData['shipping_address'] ?? null,
                    'tracking_status' => 'PENDING',
                ]);
            }

            $variants = $this->inventory->resolveVariants(array_keys($items));

            foreach ($result['items'] as $line) {
                $order->items()->create([
                    'product_variant_id' => $line['variant_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $line['subtotal'],
                ]);

                $variant = $variants->get($line['sku']);

                if ($variant) {
                    $this->inventory->decrementStock($variant, $line['quantity']);
                    $freshVariant = $variant->fresh();
                    if ($freshVariant && $freshVariant->needs_restock) {
                        event(new \App\Events\LowStockAlertEvent($freshVariant));
                    }
                }
            }

            $order = $order->load(['items.variant.product', 'user']);

            event(new \App\Events\OrderCreatedEvent($order));

            return $order;
        });
    }
```

Modify `app/Http/Controllers/Api/V1/OrderController.php`:
Pass validated extra fields into `checkout`:
```php
    public function checkout(CartValidateRequest $request): JsonResponse
    {
        try {
            $shippingData = $request->only([
                'shipping_address',
                'destination_postal_code',
                'courier_code',
                'courier_service',
                'shipping_cost',
                'payment_method',
            ]);
            $order = $this->checkout->checkout($request->user(), $request->cartItems(), $shippingData);
        } catch (InsufficientStockException $e) {
...
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=StorefrontCheckoutTest`
Expected: PASS (1 test, 4 assertions).

- [ ] **Step 5: Run full test suite to guarantee zero regressions**

Run: `php artisan test`
Expected: PASS (51 tests passing, 0 failures).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Api/V1/CartValidateRequest.php app/Services/CheckoutService.php app/Http/Controllers/Api/V1/OrderController.php tests/Feature/StorefrontCheckoutTest.php
git commit -m "feat: extend checkout API to support shipping options and shipping order creation"
```

---

### Task 2: Route Data Pipeline for Storefront Catalog

**Files:**
- Modify: `routes/web.php:10-23`
- Test: `tests/Feature/StorefrontCatalogViewTest.php`

**Interfaces:**
- Consumes: `Product::with(['category', 'variants', 'compatibleVehicles'])`
- Produces: `$products` collection passed to `view('welcome')` for catalog rendering.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/StorefrontCatalogViewTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vehicle;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_storefront_receives_active_products_with_variants_and_compatible_vehicles(): void
    {
        $vehicle = Vehicle::create(['brand' => 'Honda', 'model' => 'Vario 160', 'year' => 2023]);
        $category = Category::create(['name' => 'Pengereman', 'slug' => 'pengereman']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Brakepad Daytona Super',
            'slug' => 'brakepad-daytona-super',
            'brand' => 'Daytona',
            'base_price' => 85000,
            'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'DAY-BP-001',
            'variant_name' => 'Gold Edition',
            'additional_price' => 15000,
            'stock' => 5,
        ]);
        $product->compatibleVehicles()->attach($vehicle->id, ['notes' => 'Plug and Play']);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('products');
        $response->assertViewHas('vehicles');
        $response->assertViewHas('categories');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StorefrontCatalogViewTest`
Expected: FAIL with `assertViewHas('products')` failed.

- [ ] **Step 3: Update `routes/web.php`**

Modify `routes/web.php` lines 10-23:
```php
Route::get('/', function () {
    $vehicles = Vehicle::orderBy('brand')->orderBy('model')->get();
    $categories = Category::orderBy('name')->get();
    $warehouses = Warehouse::orderBy('name')->get();
    $products = Product::with(['category', 'variants', 'compatibleVehicles'])
        ->where('is_active', true)
        ->latest('id')
        ->get();
    $stats = [
        'vehicles_count' => Vehicle::count(),
        'products_count' => Product::count(),
        'categories_count' => Category::count(),
        'warehouses_count' => Warehouse::count(),
    ];

    return view('welcome', compact('vehicles', 'categories', 'warehouses', 'products', 'stats'));
});
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=StorefrontCatalogViewTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php tests/Feature/StorefrontCatalogViewTest.php
git commit -m "feat: pass active products with variants and vehicles to storefront"
```

---

### Task 3: Alpine.js Reactive State Management (Cart, Garage, Auth Stores)

**Files:**
- Modify: `resources/views/welcome.blade.php` (add Alpine stores script in `<head>` or before `</body>`)

**Interfaces:**
- Consumes: CDN Alpine.js (`https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js`)
- Produces: `Alpine.store('cart')`, `Alpine.store('garage')`, `Alpine.store('auth')` accessible by all UI components.

- [ ] **Step 1: Write Alpine stores initialization script**

Create the JavaScript stores definition for Alpine:
```html
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>
<script>
    document.addEventListener('alpine:init', () => {
        // --- AUTH STORE ---
        Alpine.store('auth', {
            token: localStorage.getItem('motovault_token') || null,
            user: JSON.parse(localStorage.getItem('motovault_user') || 'null'),
            isAuthModalOpen: false,
            authTab: 'login', // 'login' or 'register'
            authError: '',
            isLoading: false,

            get isAuthenticated() {
                return !!this.token;
            },

            setAuth(token, user) {
                this.token = token;
                this.user = user;
                localStorage.setItem('motovault_token', token);
                localStorage.setItem('motovault_user', JSON.stringify(user));
            },

            logout() {
                this.token = null;
                this.user = null;
                localStorage.removeItem('motovault_token');
                localStorage.removeItem('motovault_user');
            }
        });

        // --- GARAGE STORE ---
        Alpine.store('garage', {
            selectedVehicleId: localStorage.getItem('motovault_garage_id') || '',
            selectedVehicleName: localStorage.getItem('motovault_garage_name') || '',

            setVehicle(id, name) {
                this.selectedVehicleId = id;
                this.selectedVehicleName = name;
                if (id) {
                    localStorage.setItem('motovault_garage_id', id);
                    localStorage.setItem('motovault_garage_name', name);
                } else {
                    localStorage.removeItem('motovault_garage_id');
                    localStorage.removeItem('motovault_garage_name');
                }
            },

            clearVehicle() {
                this.setVehicle('', '');
            }
        });

        // --- CART STORE ---
        Alpine.store('cart', {
            items: JSON.parse(localStorage.getItem('motovault_cart') || '[]'),
            isDrawerOpen: false,
            isCheckoutOpen: false,
            isOrderTrackerOpen: false,
            checkoutStep: 1,
            isCheckingOut: false,
            checkoutError: '',
            checkoutSuccessData: null,

            // Shipping selection
            postalCode: localStorage.getItem('motovault_postal') || '12190',
            recipientName: '',
            recipientPhone: '',
            recipientEmail: '',
            recipientAddress: '',
            shippingRates: [],
            selectedCourier: null,
            isLoadingRates: false,
            paymentMethod: 'qris',

            get count() {
                return this.items.reduce((sum, item) => sum + item.quantity, 0);
            },

            get subtotal() {
                return this.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            },

            get shippingCost() {
                return this.selectedCourier ? Number(this.selectedCourier.cost) : 0;
            },

            get totalAmount() {
                return this.subtotal + this.shippingCost;
            },

            save() {
                localStorage.setItem('motovault_cart', JSON.stringify(this.items));
                if (this.postalCode) localStorage.setItem('motovault_postal', this.postalCode);
            },

            addItem(variant) {
                const existing = this.items.find(i => i.sku === variant.sku);
                if (existing) {
                    if (existing.quantity < variant.stock) {
                        existing.quantity++;
                    } else {
                        alert('Maksimal stok tercapai untuk varian ini.');
                        return;
                    }
                } else {
                    this.items.push({
                        variant_id: variant.id,
                        sku: variant.sku,
                        name: variant.name,
                        variant_name: variant.variant_name,
                        price: Number(variant.price),
                        stock: variant.stock,
                        quantity: 1,
                        image_url: variant.image_url || null,
                        weight_gram: variant.weight_gram || 500
                    });
                }
                this.save();
                this.isDrawerOpen = true;
            },

            updateQty(sku, qty) {
                const item = this.items.find(i => i.sku === sku);
                if (!item) return;
                const newQty = parseInt(qty);
                if (newQty <= 0) {
                    this.removeItem(sku);
                } else if (newQty <= item.stock) {
                    item.quantity = newQty;
                    this.save();
                } else {
                    item.quantity = item.stock;
                    this.save();
                    alert(`Stok hanya tersedia ${item.stock} pcs.`);
                }
            },

            removeItem(sku) {
                this.items = this.items.filter(i => i.sku !== sku);
                this.save();
            },

            clearCart() {
                this.items = [];
                this.save();
            }
        });
    });
</script>
```

- [ ] **Step 2: Add Alpine script and stores to `resources/views/welcome.blade.php`**

Ensure `welcome.blade.php` includes the Alpine CDN and Store definitions inside the document.

- [ ] **Step 3: Verify page loads cleanly without JS errors**

Run: `php artisan test`
Verify that `GET /` returns status 200 with no PHP errors.

- [ ] **Step 4: Commit**

```bash
git add resources/views/welcome.blade.php
git commit -m "feat: implement Alpine.js reactive stores for cart, garage, and auth"
```

---

### Task 4: Storefront UI — Navigation Header, My Garage Sticky Bar & Catalog Grid

**Files:**
- Modify: `resources/views/welcome.blade.php`

**Interfaces:**
- Consumes: `$products`, `$categories`, `$vehicles`, `$stats`
- Produces:
  - Interactive top navbar with Cart counter badge & Auth button.
  - "My Garage" bar with motorcycle selector, active badge, and reset button.
  - Category tabs & Search bar.
  - Product catalog cards with compatibility check against `Alpine.store('garage').selectedVehicleId` and "Beli / Tambah ke Keranjang" buttons.

- [ ] **Step 1: Implement Navigation Header with Cart Badge & Auth Button**

Replace/update header in `welcome.blade.php`:
- Include cart button with `$store.cart.count` badge.
- Include "Garasi Saya" quick indicator.
- Include customer auth button (`$store.auth.isAuthenticated ? $store.auth.user.name : 'Masuk / Daftar'`).

- [ ] **Step 2: Implement "My Garage" Filter Bar**

Add sticky or hero-level vehicle selector bar:
- Dropdown populated with `$vehicles`.
- When selected, updates `$store.garage.setVehicle(id, name)`.
- Shows a glowing emerald banner: *"Motor Anda: [Nama Motor] — Katalog menampilkan sparepart yang pasti pas."* with a "Ganti / Hapus" button.

- [ ] **Step 3: Implement Live Product Catalog Grid**

Add a dedicated section `#katalog-produk`:
- Category pills filter (Semua, Oli, Pengereman, Mesin, Suspensi, etc.).
- Search input.
- Grid of cards rendering `$products`.
- For each product:
  - Image / Icon placeholder.
  - Category pill & Brand badge.
  - Product Name & Base Price formatted as Rupiah.
  - Compatibility Badge: If `$store.garage.selectedVehicleId` matches one of the product's compatible vehicle IDs, display a prominent green badge: `✓ Pasti Pas untuk [Motor]`. If garage is set but not compatible, display subtle `⚠️ Belum terverifikasi untuk [Motor]`.
  - Variant selector or primary variant "Tambah ke Keranjang" button calling `$store.cart.addItem(...)`.

- [ ] **Step 4: Verify catalog renders and cart count reacts**

Run: `php artisan test --filter=StorefrontCatalogViewTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/welcome.blade.php
git commit -m "feat: add interactive navbar, My Garage compatibility bar, and product catalog grid"
```

---

### Task 5: Slide-Over Cart Drawer & Floating Cart Pill

**Files:**
- Modify: `resources/views/welcome.blade.php`

**Interfaces:**
- Consumes: `$store.cart.items`, `$store.cart.subtotal`, `$store.cart.count`, `$store.cart.isDrawerOpen`
- Produces:
  - Floating Cart Pill on bottom-right displaying item count and subtotal.
  - Slide-Over Drawer sliding from right with smooth backdrop.
  - Quantity controls (`-`, number input, `+`), remove button, and Subtotal summary.
  - "Lanjut ke Pembayaran" button that triggers checkout modal.

- [ ] **Step 1: Implement Floating Cart Pill**

Add sticky bottom-right button:
- Visible when `$store.cart.count > 0`.
- Displays shopping cart icon, count badge, and formatted `Rp ${$store.cart.subtotal.toLocaleString('id-ID')}`.
- Click triggers `$store.cart.isDrawerOpen = true`.

- [ ] **Step 2: Implement Slide-Over Cart Drawer Markup**

Add drawer markup with Alpine transitions:
```html
<div x-data x-show="$store.cart.isDrawerOpen" class="fixed inset-0 z-50 overflow-hidden" style="display: none;">
    <!-- Backdrop -->
    <div x-show="$store.cart.isDrawerOpen" 
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="$store.cart.isDrawerOpen = false" 
         class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>

    <!-- Drawer Panel -->
    <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
        <div x-show="$store.cart.isDrawerOpen"
             x-transition:enter="transform transition ease-in-out duration-300"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transform transition ease-in-out duration-300"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="w-screen max-w-md bg-[#0e1524] border-l border-gray-800 text-white flex flex-col shadow-2xl">
            <!-- Header -->
            <!-- Items list with Qty buttons -->
            <!-- Footer with Subtotal & Checkout CTA -->
        </div>
    </div>
</div>
```

- [ ] **Step 3: Implement Empty State & Stock Alerts in Drawer**

When items list is empty, display friendly illustration and "Mulai Belanja" button.
When an item's quantity reaches variant stock limit, display "Stok Maksimal".

- [ ] **Step 4: Test in browser / automated route check**

Verify view renders cleanly with `php artisan test`.

- [ ] **Step 5: Commit**

```bash
git add resources/views/welcome.blade.php
git commit -m "feat: add slide-over cart drawer and floating cart pill with Alpine.js"
```

---

### Task 6: 3-Step Checkout Modal with Live 3PL Rates & Payment

**Files:**
- Modify: `resources/views/welcome.blade.php`

**Interfaces:**
- Consumes:
  - `POST /api/v1/shipping/rates` for live 3PL rates.
  - `POST /api/v1/orders/checkout` for order placement.
  - `POST /api/v1/auth/register` and `login` for seamless inline auth.
- Produces:
  - Step 1: Customer Contact & Delivery Address.
  - Step 2: Courier selection (JNE, J&T, SiCepat) with live cost and dynamic total.
  - Step 3: Payment method (QRIS, VA, Bank Transfer) and Order confirmation.

- [ ] **Step 1: Implement Checkout Modal Structure with 3 Steps**

Add Alpine-driven modal to `welcome.blade.php`:
- Stepper indicator: 1. Alamat -> 2. Pengiriman -> 3. Pembayaran.
- Step 1:
  - Input: Name, Phone, Email, Address, Postal Code (5 digits).
  - If user is not logged in, auto-fill or offer instant account creation.
  - Button: "Lanjut: Pilih Ekspedisi" (triggers rate fetch via `fetchShippingRates()`).
- Step 2:
  - Calls `POST /api/v1/shipping/rates` with `destination_postal_code` and cart items.
  - Renders courier options: JNE (REG, YES), J&T (EZ), SiCepat (BEST).
  - Selecting a courier updates `$store.cart.selectedCourier`.
  - Button: "Lanjut: Pembayaran".
- Step 3:
  - Review items, shipping fee, total amount.
  - Select payment method (QRIS, BCA VA, Mandiri VA, Bank Transfer).
  - Button: "Bayar Sekarang" -> calls `submitCheckout()`.

- [ ] **Step 2: Implement `submitCheckout()` JavaScript Logic**

```javascript
async function submitCheckout() {
    const cart = Alpine.store('cart');
    const auth = Alpine.store('auth');
    cart.isCheckingOut = true;
    cart.checkoutError = '';

    try {
        // Ensure user is authenticated or auto-registered
        let token = auth.token;
        if (!token) {
            // Auto register customer with provided details
            const regRes = await fetch('/api/v1/auth/register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    name: cart.recipientName,
                    email: cart.recipientEmail,
                    phone: cart.recipientPhone,
                    password: 'Password123!',
                    password_confirmation: 'Password123!'
                })
            });
            const regData = await regRes.json();
            if (regData.success && regData.data.token) {
                auth.setAuth(regData.data.token, regData.data.user);
                token = regData.data.token;
            } else {
                throw new Error(regData.message || 'Gagal mendaftarkan akun untuk checkout');
            }
        }

        const payload = {
            items: cart.items.map(i => ({ sku: i.sku, quantity: i.quantity })),
            shipping_address: `${cart.recipientName} (${cart.recipientPhone}) - ${cart.recipientAddress}`,
            destination_postal_code: cart.postalCode,
            courier_code: cart.selectedCourier ? cart.selectedCourier.courier : 'jne',
            courier_service: cart.selectedCourier ? cart.selectedCourier.service : 'REG',
            shipping_cost: cart.shippingCost,
            payment_method: cart.paymentMethod
        };

        const res = await fetch('/api/v1/orders/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.message || 'Checkout gagal');
        }

        // Save order to recent orders
        const recentOrders = JSON.parse(localStorage.getItem('motovault_recent_orders') || '[]');
        recentOrders.unshift(data.data.order_number);
        localStorage.setItem('motovault_recent_orders', JSON.stringify(recentOrders));

        cart.checkoutSuccessData = data.data;
        cart.clearCart();
        cart.isCheckoutOpen = false;
    } catch (err) {
        cart.checkoutError = err.message;
    } finally {
        cart.isCheckingOut = false;
    }
}
```

- [ ] **Step 3: Verify checkout flow with automated tests**

Run: `php artisan test`
Expected: All 51 tests pass.

- [ ] **Step 4: Commit**

```bash
git add resources/views/welcome.blade.php
git commit -m "feat: implement 3-step checkout modal with live 3PL rates and payment confirmation"
```

---

### Task 7: Order Success Screen, Customer Orders Drawer & 3PL Live Tracking

**Files:**
- Modify: `resources/views/welcome.blade.php`

**Interfaces:**
- Consumes: `GET /api/v1/orders`, `GET /api/v1/shipping/track/{waybill}`
- Produces:
  - Success modal with Order Invoice, Payment Instructions (QRIS / VA code), and "Lacak Pesanan" button.
  - "Pesanan Saya" slide-over drawer showing customer orders with fulfillment status.
  - Integrated 3PL waybill tracking viewer.

- [ ] **Step 1: Implement Order Success Modal**

Add success modal triggered when `cart.checkoutSuccessData` is not null:
- Checkmark animation.
- Order number (`ORD-20260918-XXXX`).
- Payment method & virtual account / QRIS display.
- CTA button: "Lihat Status Pesanan" (opens Orders Drawer).

- [ ] **Step 2: Implement "Pesanan Saya" Customer Orders Drawer**

Add drawer opening via navbar "Pesanan Saya" or success modal:
- Fetches `/api/v1/orders` using `Alpine.store('auth').token`.
- If unauthenticated, displays recent orders stored in `localStorage.getItem('motovault_recent_orders')`.
- Displays order items, total paid, payment status badge (Unpaid / Paid), fulfillment status (Pending / Processing / Shipped / Delivered).
- If `tracking_number` exists, displays button "Lacak Resi 3PL" that triggers the live tracking timeline.

- [ ] **Step 3: Connect Live 3PL Waybill Tracking**

Wire up `GET /api/v1/shipping/track/{waybill}` to display the multi-checkpoint logistics status in the drawer.

- [ ] **Step 4: Quick Customer Auth Modal (Login / Register)**

Add a clean modal for direct customer login or registration when clicking "Masuk / Daftar" on the navbar:
- Login Tab: Email + Password -> calls `POST /api/v1/auth/login`.
- Register Tab: Name, Email, Phone, Password -> calls `POST /api/v1/auth/register`.
- Upon success, saves token and updates UI immediately.

- [ ] **Step 5: Commit**

```bash
git add resources/views/welcome.blade.php
git commit -m "feat: add order success modal, customer orders drawer, and live 3PL waybill tracking"
```

---

### Task 8: End-to-End Verification & Polish

**Files:**
- Test: `tests/Feature/StorefrontEndToEndFlowTest.php`
- Modify: `resources/views/welcome.blade.php` (styling & responsive tweaks)

**Interfaces:**
- Consumes: All endpoints and view components
- Produces: Fully verified, industry-grade storefront experience.

- [ ] **Step 1: Write comprehensive end-to-end feature test**

Create `tests/Feature/StorefrontEndToEndFlowTest.php`:
- Verify:
  1. Storefront page renders with products, categories, warehouses.
  2. Cart validation endpoint returns valid stock and price.
  3. Shipping rates endpoint returns JNE, J&T, SiCepat for cart items.
  4. Checkout creates Order + ShippingOrder with correct amounts and decrements inventory.
  5. Orders list returns the newly placed order.

- [ ] **Step 2: Run all feature tests**

Run: `php artisan test`
Expected: 100% tests passing (52+ tests, 0 failures).

- [ ] **Step 3: Manual / Browser Visual Verification**

Verify responsiveness, colors, typography, and interactive Alpine stores via web view or curl/browser check.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/StorefrontEndToEndFlowTest.php resources/views/welcome.blade.php
git commit -m "test: add comprehensive end-to-end storefront flow test and polish UI"
```
