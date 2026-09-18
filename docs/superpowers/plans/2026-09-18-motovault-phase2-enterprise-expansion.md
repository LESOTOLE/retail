# MotoVault Phase 2: Enterprise Expansion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform MotoVault into an enterprise omnichannel platform with real-time AI Streaming SSE, RAG Semantic Symptom Diagnostics, Live 3PL Shipping Rates, Laravel Reverb WebSockets, Multi-Warehouse Proximity Routing, and a dedicated SPA/POS Dashboard.

**Architecture:** Laravel 11 backend with Laravel Reverb WebSockets, Google Gemini 1.5/2.0 Streaming & Embeddings, Redis 7+ cache & message broker, and Vue 3 / React SPA frontend interfaces.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL 8, Redis 7, Laravel Reverb, Google Gemini API, Biteship / RajaOngkir API, Midtrans Snap, Tailwind CSS, Pest PHP.

## Global Constraints
- Laravel 11 with PHP 8.3+ typing and backed enums.
- All JSON responses follow the standard MotoVault ApiResponse envelope (`success`, `message`, `data`, `meta`, `errors`).
- All inventory mutations must use `lockForUpdate()` within `DB::transaction()`.
- Zero-hallucination guardrails for AI tool calls.
- Automated tests required for every new feature before task completion.

---

### Task 1: AI Streaming SSE (Server-Sent Events) & Typewriter Response

**Files:**
- Create: `app/Services/AI/GeminiStreamingService.php`
- Create: `app/Http/Controllers/Api/V1/AiStreamingChatController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/AiStreamingChatTest.php`

**Interfaces:**
- Consumes: `AiChatSession`, `Vehicle`, `ProductToolExecutor`
- Produces: `POST /api/v1/ai/chat/stream` returning `text/event-stream` SSE tokens and tool call results.

- [ ] **Step 1: Write the failing feature test for SSE streaming**

```php
// tests/Feature/AiStreamingChatTest.php
public function test_ai_streaming_endpoint_returns_event_stream_response(): void
{
    $response = $this->post('/api/v1/ai/chat/stream', [
        'message' => 'Rekomendasikan oli motor',
    ]);

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `php artisan test --filter=AiStreamingChatTest`
Expected: FAIL (Route not found / Controller missing)

- [ ] **Step 3: Implement `GeminiStreamingService` and `AiStreamingChatController`**
- Implement `streamGenerateContent` Gemini API consumption.
- Stream chunks with `event: token\ndata: {"text":"..."}\n\n` and flush output buffer.
- Fallback streaming parser when Gemini API key is missing.

- [ ] **Step 4: Register route in `routes/api.php`**
```php
Route::post('/ai/chat/stream', [AiStreamingChatController::class, 'stream'])->name('api.v1.ai.chat.stream');
```

- [ ] **Step 5: Run test to verify it passes**
Run: `php artisan test --filter=AiStreamingChatTest`
Expected: PASS

---

### Task 2: RAG Semantic Search & Vector Embeddings for Symptom Diagnostics

**Files:**
- Create: `database/migrations/2026_09_18_090000_create_diagnostic_symptoms_table.php`
- Create: `app/Models/DiagnosticSymptom.php`
- Create: `app/Services/AI/GeminiEmbeddingService.php`
- Create: `app/Services/AI/DiagnosticRagService.php`
- Modify: `app/Services/AI/ProductToolExecutor.php` (Add `diagnose_symptom` tool)
- Test: `tests/Feature/DiagnosticRagTest.php`

**Interfaces:**
- Consumes: User complaint string (e.g., "motor gredek saat nanjak"), Gemini `text-embedding-004`
- Produces: Matched symptoms, suspected root cause, and compatible parts array.

- [ ] **Step 1: Create migration and model for `diagnostic_symptoms`**
- Columns: `id`, `category_id`, `symptom_title`, `symptom_description`, `suspected_root_cause`, `embedding` (json), `recommended_product_ids` (json), timestamps.

- [ ] **Step 2: Write failing test for symptom diagnosis RAG**
```php
public function test_diagnose_symptom_matches_root_cause_and_products(): void
{
    $service = app(DiagnosticRagService::class);
    $result = $service->diagnose('motor bergetar gredek di tanjakan', vehicleId: 2);
    $this->assertNotEmpty($result['suspected_causes']);
}
```

- [ ] **Step 3: Implement `GeminiEmbeddingService` with Cosine Similarity Engine**
- Compute dot product / cosine similarity over JSON stored embedding vectors.
- Add tool `diagnose_symptom` into `ProductToolExecutor`.

- [ ] **Step 4: Seed standard motorcycle diagnostic symptoms**
- CVT vibration/gredek, electrical/battery drop, brake squeal, fork oil leak, engine misfire.

- [ ] **Step 5: Run tests and verify**
Run: `php artisan test --filter=DiagnosticRagTest`
Expected: PASS

---

### Task 3: Live 3PL Logistics & Shipping Rates Integration (Biteship / RajaOngkir)

**Files:**
- Create: `database/migrations/2026_09_18_091000_add_dimensions_to_products_and_variants.php`
- Create: `database/migrations/2026_09_18_092000_create_shipping_orders_table.php`
- Create: `app/Models/ShippingOrder.php`
- Create: `app/Services/Shipping/ShippingRateService.php`
- Create: `app/Http/Controllers/Api/V1/ShippingController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/ShippingRatesApiTest.php`

**Interfaces:**
- Consumes: Cart items, total weight calculation, origin postal code, destination postal code.
- Produces: List of couriers (JNE, J&T, SiCepat, GoSend) with rate, estimated days, and service code.

- [ ] **Step 1: Add dimension and weight columns to products and variants**
- `weight_gram`, `length_cm`, `width_cm`, `height_cm`.

- [ ] **Step 2: Write failing test for shipping rate calculation**
```php
public function test_can_calculate_shipping_rates_for_cart_items(): void
{
    $response = $this->postJson('/api/v1/shipping/rates', [
        'items' => [['sku' => 'MOT-10W40-1L', 'quantity' => 2]],
        'destination_postal_code' => '12190',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['rates' => [['courier_code', 'service', 'cost']]]]);
}
```

- [ ] **Step 3: Implement `ShippingRateService` with Redis Rate Caching**
- Integrate Biteship / RajaOngkir API format with fallback rate table.
- Implement `ShippingController::rates()` and `ShippingController::track()`.

- [ ] **Step 4: Run tests and verify**
Run: `php artisan test --filter=ShippingRatesApiTest`
Expected: PASS

---

### Task 4: Real-Time WebSockets via Laravel Reverb

**Files:**
- Create: `config/reverb.php`
- Create: `app/Events/OrderCreatedEvent.php`
- Create: `app/Events/OrderStatusUpdatedEvent.php`
- Create: `app/Events/LowStockAlertTriggeredEvent.php`
- Modify: `app/Services/CheckoutService.php` (Dispatch events on order state changes)
- Test: `tests/Feature/WebSocketBroadcastTest.php`

**Interfaces:**
- Consumes: Order lifecycle triggers
- Produces: Real-time event broadcasts over channels: `private-orders.{userId}`, `presence-store.{warehouseId}`, `private-admin.inventory`.

- [ ] **Step 1: Configure Laravel Reverb Broadcast Driver**
- Set `BROADCAST_CONNECTION=reverb` in `.env` and configure channel authorization routes in `routes/channels.php`.

- [ ] **Step 2: Write failing test for broadcast events**
```php
public function test_order_creation_broadcasts_order_created_event(): void
{
    Event::fake([OrderCreatedEvent::class]);
    // Perform checkout
    Event::assertDispatched(OrderCreatedEvent::class);
}
```

- [ ] **Step 3: Implement Event Classes with `ShouldBroadcast` interface**
- Attach broadcast payload containing order number, customer name, total amount, and fulfillment status.

- [ ] **Step 4: Run tests and verify**
Run: `php artisan test --filter=WebSocketBroadcastTest`
Expected: PASS

---

### Task 5: Multi-Branch & Multi-Warehouse Inventory Management

**Files:**
- Create: `database/migrations/2026_09_18_093000_create_warehouses_and_transfers_tables.php`
- Create: `app/Models/Warehouse.php`
- Create: `app/Models/WarehouseStock.php`
- Create: `app/Models/StockTransfer.php`
- Create: `app/Services/Inventory/WarehouseRoutingService.php`
- Create: `app/Http/Controllers/Api/V1/Admin/WarehouseAdminController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/MultiWarehouseRoutingTest.php`

**Interfaces:**
- Consumes: Customer coordinate $(lat, long)$, item list
- Produces: Nearest fulfillment branch with complete stock, inter-branch stock transfer requests.

- [ ] **Step 1: Create migrations for `warehouses`, `warehouse_stocks`, `stock_transfers`**
- Add foreign keys, indexes, and unique constraints.

- [ ] **Step 2: Write failing test for nearest warehouse Haversine routing**
```php
public function test_routes_checkout_to_nearest_warehouse_with_stock(): void
{
    $service = app(WarehouseRoutingService::class);
    $warehouse = $service->findNearestFulfillmentHub($customerLat, $customerLong, $items);
    $this->assertEquals('WH-JKT-01', $warehouse->code);
}
```

- [ ] **Step 3: Implement `WarehouseRoutingService` & `WarehouseAdminController`**
- Haversine distance calculation in SQL/PHP.
- Stock transfer workflow: `draft` -> `requested` -> `in_transit` -> `completed` with atomic locking.

- [ ] **Step 4: Run tests and verify**
Run: `php artisan test --filter=MultiWarehouseRoutingTest`
Expected: PASS

---

### Task 6: Full-Featured Frontend SPA & Admin/POS Dashboard

**Files:**
- Create: `resources/js/components/storefront/GarageDrawer.vue` (or React)
- Create: `resources/js/components/storefront/AiStreamingDrawer.vue`
- Create: `resources/js/components/pos/PosCashierView.vue`
- Create: `resources/js/components/admin/CompatibilityMatrixEditor.vue`
- Modify: `resources/views/welcome.blade.php`
- Test: `tests/Feature/SpaViewRenderingTest.php`

**Interfaces:**
- Consumes: REST APIs v1, SSE Streaming, Reverb WebSockets.
- Produces: Responsive UI for Storefront, POS Barcode Billing, and Admin HQ.

- [ ] **Step 1: Integrate EventSource SSE reader in AI Chat UI**
- Handle `event: token` to render typewriter character stream.
- Handle `event: product_recommendations` to render interactive buy cards.

- [ ] **Step 2: Build POS Fast Barcode Scanner Mode**
- Hotkey triggers, instant SKU lookup, thermal receipt printer format preview.

- [ ] **Step 3: Build Admin Matrix Compatibility Mass-Editor**
- Checkbox grid for mass linking parts to multiple vehicle models with notes.

- [ ] **Step 4: Run full test suite and verify**
Run: `php artisan test`
Expected: All tests pass.
