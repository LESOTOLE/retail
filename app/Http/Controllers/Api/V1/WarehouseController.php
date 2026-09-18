<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RouteNearestWarehouseRequest;
use App\Models\Warehouse;
use App\Services\Warehouse\WarehouseRoutingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Informasi Gudang, Cabang & Proximity Routing (PRD Phase 2 - Feature 5).
 */
class WarehouseController extends Controller
{
    public function __construct(
        protected WarehouseRoutingService $routingService
    ) {}

    /**
     * GET /api/v1/warehouses
     * Daftar seluruh gudang / cabang aktif (opsional urut jarak via ?latitude=...&longitude=...).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->filled('latitude') && $request->filled('longitude')) {
            $lat = (float) $request->input('latitude');
            $lng = (float) $request->input('longitude');
            $warehouses = $this->routingService->getWarehousesByProximity($lat, $lng);

            return ApiResponse::success($warehouses, 'Daftar gudang terdekat berhasil diambil');
        }

        $warehouses = Warehouse::query()->active()->get();

        return ApiResponse::success($warehouses, 'Daftar gudang cabang berhasil diambil');
    }

    /**
     * GET /api/v1/warehouses/{id}
     * Detail gudang beserta daftar stok varian produk.
     */
    public function show(int $id): JsonResponse
    {
        $warehouse = Warehouse::with(['stocks.variant.product'])->findOrFail($id);

        return ApiResponse::success($warehouse, 'Detail gudang berhasil diambil');
    }

    /**
     * POST /api/v1/warehouses/route-nearest
     * Cari gudang terdekat yang memiliki stok penuh untuk item keranjang belanja.
     */
    public function routeNearest(RouteNearestWarehouseRequest $request): JsonResponse
    {
        $result = $this->routingService->routeNearestWarehouse(
            userLat: (float) $request->input('latitude'),
            userLng: (float) $request->input('longitude'),
            cartItems: $request->input('items', [])
        );

        return ApiResponse::success($result, 'Pencarian routing gudang terdekat selesai.');
    }
}
