<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminStockAdjustmentRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\ProductVariant;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Penyesuaian Stok & Pemantauan Safety Stock Alert (PRD 4.2 & 6.8).
 */
class VariantStockController extends Controller
{
    /**
     * GET /api/v1/admin/variants/low-stock
     * Mengambil daftar varian yang berada pada atau di bawah batas minimum stok (safety stock alert).
     */
    public function lowStock(Request $request): JsonResponse
    {
        $paginator = ProductVariant::query()
            ->with('product:id,name,brand,category_id')
            ->lowStock()
            ->orderBy('stock')
            ->paginate((int) $request->integer('per_page', 20))
            ->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            ProductVariantResource::collection($paginator),
            'Daftar varian dengan stok menipis berhasil diambil'
        );
    }

    /**
     * PUT /api/v1/admin/variants/{id}/stock
     * Melakukan stock adjustment (set / increment / decrement).
     */
    public function adjustStock(AdminStockAdjustmentRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id): JsonResponse {
            $variant = ProductVariant::where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            $mode = $request->validated('mode');
            $amount = (int) $request->validated('amount');

            $newStock = match ($mode) {
                'set' => $amount,
                'increment' => $variant->stock + $amount,
                'decrement' => max(0, $variant->stock - $amount),
            };

            $variant->forceFill(['stock' => $newStock])->save();

            $variant->load('product');

            if ($variant->needs_restock) {
                event(new \App\Events\LowStockAlertEvent($variant));
            }

            return ApiResponse::success(
                new ProductVariantResource($variant),
                'Stok varian berhasil disesuaikan'
            );
        });
    }
}
