<?php

use App\Http\Controllers\Api\V1\Admin\AiLogController;
use App\Http\Controllers\Api\V1\Admin\OrderAdminController;
use App\Http\Controllers\Api\V1\Admin\ProductAdminController;
use App\Http\Controllers\Api\V1\Admin\VariantStockController;
use App\Http\Controllers\Api\V1\AiChatController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\Pos\PosOrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - MotoVault API v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Health Check & System Status (PRD 9.2)
    Route::get('/health', [HealthController::class, 'check'])->name('api.v1.health');

    // 6.1 Authentication & Profiles
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
        Route::post('/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::get('/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        });
    });

    // 6.2 Master Kendaraan & Kategori
    Route::get('/vehicles', [VehicleController::class, 'index'])->name('api.v1.vehicles.index');
    Route::get('/categories', [CategoryController::class, 'index'])->name('api.v1.categories.index');

    // 6.2 Katalog Produk & Detail
    Route::get('/products', [ProductController::class, 'index'])->name('api.v1.products.index');
    Route::get('/products/{slug}', [ProductController::class, 'show'])->name('api.v1.products.show');

    // 6.3 Validasi Keranjang (Cart)
    Route::post('/cart/validate', [CartController::class, 'validateCart'])->name('api.v1.cart.validate');

    // 6.3 Transaksi & Pesanan Pelanggan (Customer)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/orders/checkout', [OrderController::class, 'checkout'])->name('api.v1.orders.checkout');
        Route::get('/orders', [OrderController::class, 'index'])->name('api.v1.orders.index');
        Route::get('/orders/{order_number}', [OrderController::class, 'show'])->name('api.v1.orders.show');
    });

    // 6.4 AI Sales & Compatibility Assistant (Guest / Authenticated)
    Route::post('/ai/chat', [AiChatController::class, 'chat'])->name('api.v1.ai.chat');
    Route::post('/ai/chat/stream', [\App\Http\Controllers\Api\V1\AiStreamingChatController::class, 'stream'])->name('api.v1.ai.chat.stream');

    // 6.6 Payment Gateway Callback Webhook (Idempotent)
    Route::post('/webhooks/payment', [PaymentWebhookController::class, 'handle'])->name('api.v1.webhooks.payment');

    // 6.7 3PL Shipping Rates & Waybill Tracking (PRD Phase 2 - Feature 3)
    Route::post('/shipping/rates', [\App\Http\Controllers\Api\V1\ShippingController::class, 'rates'])->name('api.v1.shipping.rates');
    Route::get('/shipping/track/{waybill_number}', [\App\Http\Controllers\Api\V1\ShippingController::class, 'track'])->name('api.v1.shipping.track');

    // 6.8 Multi-Warehouse Proximity & Branch Inventory (PRD Phase 2 - Feature 5)
    Route::get('/warehouses', [\App\Http\Controllers\Api\V1\WarehouseController::class, 'index'])->name('api.v1.warehouses.index');
    Route::get('/warehouses/{id}', [\App\Http\Controllers\Api\V1\WarehouseController::class, 'show'])->name('api.v1.warehouses.show');
    Route::post('/warehouses/route-nearest', [\App\Http\Controllers\Api\V1\WarehouseController::class, 'routeNearest'])->name('api.v1.warehouses.route_nearest');

    // 6.8 POS Kasir Toko Fisik (Staff / Admin)
    Route::middleware(['auth:sanctum', 'role:staff|admin'])->prefix('pos')->group(function () {
        Route::post('/orders', [PosOrderController::class, 'store'])->name('api.v1.pos.orders.store');
    });

    // 6.8 Staff & Warehouse Admin Operations (Staff / Admin)
    Route::middleware(['auth:sanctum', 'role:staff|admin'])->prefix('admin')->group(function () {
        // Stock Adjustments & Low Stock Alert
        Route::get('/variants/low-stock', [VariantStockController::class, 'lowStock'])->name('api.v1.admin.variants.low_stock');
        Route::put('/variants/{id}/stock', [VariantStockController::class, 'adjustStock'])->name('api.v1.admin.variants.adjust_stock');

        // Order Management & Logistical Fulfillment
        Route::get('/orders', [OrderAdminController::class, 'index'])->name('api.v1.admin.orders.index');
        Route::patch('/orders/{order_number}/fulfill', [OrderAdminController::class, 'fulfill'])->name('api.v1.admin.orders.fulfill');

        // Shipping AWB generation
        Route::post('/shipping/create-awb', [\App\Http\Controllers\Api\V1\ShippingController::class, 'createWaybill'])->name('api.v1.admin.shipping.create_awb');

        // Inter-warehouse Stock Transfers
        Route::get('/warehouses/transfers', [\App\Http\Controllers\Api\V1\Admin\StockTransferAdminController::class, 'index'])->name('api.v1.admin.warehouses.transfers.index');
        Route::post('/warehouses/transfers', [\App\Http\Controllers\Api\V1\Admin\StockTransferAdminController::class, 'store'])->name('api.v1.admin.warehouses.transfers.store');
        Route::get('/warehouses/transfers/{id}', [\App\Http\Controllers\Api\V1\Admin\StockTransferAdminController::class, 'show'])->name('api.v1.admin.warehouses.transfers.show');
        Route::patch('/warehouses/transfers/{id}/status', [\App\Http\Controllers\Api\V1\Admin\StockTransferAdminController::class, 'updateStatus'])->name('api.v1.admin.warehouses.transfers.update_status');
    });

    // 6.8 Super Admin Only Operations (Admin)
    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
        // Master Product & Variant CRUD
        Route::post('/products', [ProductAdminController::class, 'store'])->name('api.v1.admin.products.store');
        Route::put('/products/{id}', [ProductAdminController::class, 'update'])->name('api.v1.admin.products.update');
        Route::delete('/products/{id}', [ProductAdminController::class, 'destroy'])->name('api.v1.admin.products.destroy');
        Route::post('/products/{id}/variants', [ProductAdminController::class, 'storeVariant'])->name('api.v1.admin.products.variants.store');
        Route::post('/products/{id}/compatibility', [ProductAdminController::class, 'syncCompatibility'])->name('api.v1.admin.products.compatibility.sync');

        // AI Chat Audit Logs
        Route::get('/ai/logs', [AiLogController::class, 'index'])->name('api.v1.admin.ai.logs');
    });
});
