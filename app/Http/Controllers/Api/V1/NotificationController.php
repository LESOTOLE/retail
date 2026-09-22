<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller untuk pemantauan notifikasi real-time & resilient fallback polling (PRD Phase 2).
 */
class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications/recent
     * Mengembalikan 10 pesanan terbaru, peringatan stok kritis, dan konfigurasi Reverb.
     */
    public function recent(Request $request): JsonResponse
    {
        // 1. Ambil 10 pesanan terbaru (online & POS)
        $recentOrders = Order::with(['user', 'items'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (Order $order) {
                return [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->user?->name ?? 'Pelanggan Toko / POS',
                    'total_amount' => (float) $order->total_amount,
                    'payment_status' => $order->payment_status?->value ?? (string) $order->payment_status,
                    'fulfillment_status' => $order->fulfillment_status?->value ?? (string) $order->fulfillment_status,
                    'payment_method' => $order->payment_method,
                    'warehouse_id' => $order->warehouse_id,
                    'items_count' => $order->items->count(),
                    'created_at' => $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ];
            });

        // 2. Ambil produk varian yang stoknya menipis (<= min_stock_alert)
        $lowStockVariants = ProductVariant::with('product')
            ->whereColumn('stock', '<=', 'min_stock_alert')
            ->orderBy('stock', 'asc')
            ->limit(10)
            ->get()
            ->map(function (ProductVariant $variant) {
                return [
                    'variant_id' => $variant->id,
                    'sku' => $variant->sku,
                    'product_name' => $variant->product?->name ?? 'Sparepart',
                    'variant_name' => $variant->variant_name,
                    'current_stock' => $variant->stock,
                    'min_stock_alert' => $variant->min_stock_alert,
                    'alert_message' => sprintf(
                        'Stok SKU %s (%s) tersisa %d unit!',
                        $variant->sku,
                        $variant->variant_name,
                        $variant->stock
                    ),
                ];
            });

        // 3. Konfigurasi publik Reverb agar frontend dapat terkoneksi
        $reverbConfig = [
            'app_key' => (string) config('broadcasting.connections.reverb.key', 'motovault-key'),
            'host' => (string) (config('broadcasting.connections.reverb.options.host') ?: 'localhost'),
            'port' => (int) config('broadcasting.connections.reverb.options.port', 8080),
            'scheme' => (string) config('broadcasting.connections.reverb.options.scheme', 'http'),
        ];

        return ApiResponse::success([
            'recent_orders' => $recentOrders,
            'low_stock_alerts' => $lowStockVariants,
            'reverb_config' => $reverbConfig,
            'unread_count' => $recentOrders->where('payment_status', 'unpaid')->count() + $lowStockVariants->count(),
        ], 'Notifikasi dan data streaming terbaru berhasil dimuat');
    }
}
