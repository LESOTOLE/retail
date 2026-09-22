<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Enums\FulfillmentStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PosOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\User;
use App\Services\CheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Transaksi Langsung Kasir Toko Fisik / Point of Sale (PRD 4.5 & 6.8).
 */
class PosOrderController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout
    ) {}

    /**
     * POST /api/v1/pos/orders
     * Membuat transaksi kasir langsung, mengunci & memotong stok,
     * serta langsung menandai pesanan sebagai lunas dan diterima pelanggan (delivered).
     */
    public function store(PosOrderRequest $request): JsonResponse
    {
        // Gunakan user kasir aktif sebagai pencatat transaksi
        $cashier = $request->user();

        try {
            $shippingData = [
                'payment_method' => $request->validated('payment_method'),
                'warehouse_id' => $request->validated('warehouse_id'),
                'shipping_address' => 'POS In-Store: '.($request->validated('customer_name') ?? 'Pelanggan Toko'),
            ];
            $order = $this->checkout->checkout($cashier, $request->cartItems(), $shippingData);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => $e->issues(),
            ], HttpResponse::HTTP_CONFLICT);
        }

        $paymentRef = $request->validated('payment_reference') ?? 'POS-'.now()->format('YmdHis');
        $paymentMethod = $request->validated('payment_method');

        $this->checkout->markAsPaid($order, $paymentRef);

        $order->forceFill([
            'payment_method' => $paymentMethod,
            'fulfillment_status' => FulfillmentStatus::Delivered,
        ])->save();

        event(new \App\Events\OrderStatusUpdatedEvent($order));

        return ApiResponse::created(
            new OrderResource($order->load(['items.variant.product', 'user'])),
            'Transaksi POS kasir berhasil diproses dan stok telah diperbarui'
        );
    }
}
