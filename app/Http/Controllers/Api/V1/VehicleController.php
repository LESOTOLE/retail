<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Master kendaraan & cascading dropdown (PRD 4.1 & 6.2).
 */
class VehicleController extends Controller
{
    /**
     * GET /api/v1/vehicles?brand=...&search=...&year=...
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand' => ['nullable', 'string', 'max:60'],
            'search' => ['nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ]);

        $vehicles = Vehicle::query()
            ->brand($validated['brand'] ?? null)
            ->search($validated['search'] ?? null)
            ->forYear($validated['year'] ?? null)
            ->orderBy('brand')
            ->orderBy('model')
            ->get();

        return ApiResponse::success(
            VehicleResource::collection($vehicles)->resolve(),
            'Daftar kendaraan berhasil diambil'
        );
    }

    /**
     * GET /api/v1/vehicles/brands - Level pertama cascading dropdown.
     */
    public function brands(): JsonResponse
    {
        $brands = Vehicle::query()
            ->select('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        return ApiResponse::success($brands, 'Daftar merek kendaraan berhasil diambil');
    }

    /**
     * GET /api/v1/vehicles/{vehicle} - Detail satu kendaraan.
     */
    public function show(Vehicle $vehicle): JsonResponse
    {
        return ApiResponse::success(
            new VehicleResource($vehicle),
            'Detail kendaraan berhasil diambil'
        );
    }
}
