<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CartValidateRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Transaksi & Checkout (PRD 6.3).
 */
class OrderController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout
    ) {}

    /**
     * POST /api/v1/orders/checkout - Membuat invoice pesanan & mengunci stok.
     */
    public function checkout(CartValidateRequest $request): JsonResponse
    {
        try {
            $shippingData = $request->safe()->only([
                'shipping_address',
                'destination_postal_code',
                'courier_code',
                'courier_service',
                'shipping_cost',
                'payment_method',
            ]);
            $order = $this->checkout->checkout($request->user(), $request->cartItems(), $shippingData);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => $e->issues(),
            ], HttpResponse::HTTP_CONFLICT);
        }

        return ApiResponse::created(
            new OrderResource($order),
            'Pesanan berhasil dibuat, silakan lanjutkan pembayaran'
        );
    }

    /**
     * GET /api/v1/orders - Riwayat pesanan milik user (staff/admin melihat semua).
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = Order::query()
            ->ownedBy($request->user())
            ->with(['items.variant.product', 'user'])
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            OrderResource::collection($paginator),
            'Daftar pesanan berhasil diambil'
        );
    }

    /**
     * GET /api/v1/orders/{order_number} - Detail status transaksi & resi.
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::query()
            ->ownedBy($request->user())
            ->with(['items.variant.product', 'user'])
            ->where('order_number', $orderNumber)
            ->first();

        if (! $order) {
            return ApiResponse::error(
                'Pesanan tidak ditemukan.',
                null,
                HttpResponse::HTTP_NOT_FOUND
            );
        }

        return ApiResponse::success(
            new OrderResource($order),
            'Detail pesanan berhasil diambil'
        );
    }
}
