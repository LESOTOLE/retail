<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminCompatibilitySyncRequest;
use App\Http\Requests\Api\V1\AdminProductStoreRequest;
use App\Http\Requests\Api\V1\AdminProductUpdateRequest;
use App\Http\Requests\Api\V1\AdminVariantStoreRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Manajemen Katalog & Kompatibilitas Produk oleh Admin (PRD 6.8).
 */
class ProductAdminController extends Controller
{
    /**
     * POST /api/v1/admin/products - Tambah master produk baru.
     */
    public function store(AdminProductStoreRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return ApiResponse::created(
            new ProductResource($product->load(['category', 'variants'])),
            'Produk berhasil ditambahkan'
        );
    }

    /**
     * PUT /api/v1/admin/products/{id} - Edit data master produk.
     */
    public function update(AdminProductUpdateRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update($request->validated());

        return ApiResponse::success(
            new ProductResource($product->load(['category', 'variants'])),
            'Produk berhasil diperbarui'
        );
    }

    /**
     * DELETE /api/v1/admin/products/{id} - Hapus produk.
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return ApiResponse::success(null, 'Produk berhasil dihapus');
    }

    /**
     * POST /api/v1/admin/products/{id}/variants - Tambah varian fisik baru.
     */
    public function storeVariant(AdminVariantStoreRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $variant = $product->variants()->create($request->validated());

        return ApiResponse::created(
            new ProductVariantResource($variant),
            'Varian produk berhasil ditambahkan'
        );
    }

    /**
     * POST /api/v1/admin/products/{id}/compatibility - Sinkronisasi matriks kendaraan.
     */
    public function syncCompatibility(AdminCompatibilitySyncRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $syncData = [];
        foreach ($request->validated('vehicles') as $item) {
            $syncData[$item['vehicle_id']] = [
                'notes' => $item['notes'] ?? null,
            ];
        }

        $product->vehicles()->sync($syncData);

        return ApiResponse::success(
            new ProductResource($product->load(['vehicles', 'category', 'variants'])),
            'Matriks kompatibilitas kendaraan berhasil diperbarui'
        );
    }
}
