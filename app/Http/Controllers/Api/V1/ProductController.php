<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductIndexRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Katalog produk dengan filter kompatibilitas kendaraan (PRD 6.2 & Task 3).
 */
class ProductController extends Controller
{
    /**
     * GET /api/v1/products
     * Filter: vehicle_id, category_id, min_price, max_price, search, brand, in_stock, sort.
     */
    public function index(ProductIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = Product::query()
            ->active()
            ->with(['category:id,name,slug', 'variants'])
            ->withCount('variants')
            ->forVehicle($filters['vehicle_id'] ?? null)
            ->forCategory($filters['category_id'] ?? null)
            ->search($filters['search'] ?? null)
            ->priceBetween($filters['min_price'] ?? null, $filters['max_price'] ?? null)
            ->when(
                filled($filters['brand'] ?? null),
                fn (Builder $q) => $q->where('brand', $filters['brand'])
            )
            ->when(
                $request->boolean('in_stock'),
                fn (Builder $q) => $q->whereHas('variants', fn (Builder $v) => $v->where('stock', '>', 0))
            );

        $this->applySort($query, $filters['sort'] ?? 'newest');

        $paginator = $query
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            ProductResource::collection($paginator),
            'Daftar produk berhasil diambil'
        );
    }

    /**
     * GET /api/v1/products/{slug}
     * Detail produk beserta varian, stok, dan kompatibilitas kendaraan.
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->with(['category', 'variants', 'vehicles'])
            ->where('slug', $slug)
            ->active()
            ->firstOrFail();

        return ApiResponse::success(
            new ProductResource($product),
            'Detail produk berhasil diambil'
        );
    }

    /**
     * Menerapkan pengurutan hasil katalog.
     *
     * @param  Builder<Product>  $query
     */
    protected function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('base_price'),
            'price_desc' => $query->orderByDesc('base_price'),
            'name_asc' => $query->orderBy('name'),
            default => $query->latest('id'),
        };
    }
}
