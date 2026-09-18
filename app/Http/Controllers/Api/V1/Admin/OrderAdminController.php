<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminFulfillOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Monitoring Pesanan & Fulfillment Logistik oleh Staff / Admin (PRD 4.5 & 6.8).
 */
class OrderAdminController extends Controller
{
    /**
     * GET /api/v1/admin/orders
     * Monitoring semua pesanan dengan filter status dan paginasi.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with(['items.variant.product', 'user'])
            ->when($request->filled('payment_status'), function (Builder $q) use ($request) {
                $q->where('payment_status', $request->input('payment_status'));
            })
            ->when($request->filled('fulfillment_status'), function (Builder $q) use ($request) {
                $q->where('fulfillment_status', $request->input('fulfillment_status'));
            })
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $term = '%'.$request->input('search').'%';
                $q->where(function (Builder $sub) use ($term) {
                    $sub->where('order_number', 'like', $term)
                        ->orWhere('tracking_number', 'like', $term)
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->latest('id');

        $paginator = $query->paginate((int) $request->integer('per_page', 15))->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            OrderResource::collection($paginator),
            'Daftar pesanan admin berhasil diambil'
        );
    }

    /**
     * PATCH /api/v1/admin/orders/{order_number}/fulfill
     * Input resi ekspedisi dan pembaruan status fulfillment (misal: processing -> shipped).
     */
    public function fulfill(AdminFulfillOrderRequest $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $order->forceFill([
            'fulfillment_status' => $request->validated('fulfillment_status'),
            'tracking_number' => $request->validated('tracking_number') ?? $order->tracking_number,
        ])->save();

        event(new \App\Events\OrderStatusUpdatedEvent($order));

        return ApiResponse::success(
            new OrderResource($order->load(['items.variant.product', 'user'])),
            'Status pemrosesan pesanan berhasil diperbarui'
        );
    }
}
