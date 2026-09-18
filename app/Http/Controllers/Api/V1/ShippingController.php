<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShippingCreateWaybillRequest;
use App\Http\Requests\Api\V1\ShippingRatesRequest;
use App\Models\Order;
use App\Services\Shipping\ShippingRateService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Controller Layanan Pengiriman & Resi Logistik 3PL (PRD Phase 2 - Feature 3).
 */
class ShippingController extends Controller
{
    public function __construct(
        protected ShippingRateService $shippingService
    ) {}

    /**
     * Hitung ongkos kirim multi-ekspedisi real-time.
     *
     * POST /api/v1/shipping/rates
     */
    public function rates(ShippingRatesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $rates = $this->shippingService->calculateRates(
            destinationPostalCode: $validated['destination_postal_code'],
            items: $validated['items'] ?? [],
            originPostalCode: $validated['origin_postal_code'] ?? ShippingRateService::DEFAULT_ORIGIN_POSTAL_CODE,
            courierFilter: $validated['courier'] ?? null
        );

        return ApiResponse::success($rates, 'Kalkulasi tarif pengiriman berhasil diambil.');
    }

    /**
     * Generate nomor resi pengiriman (AWB) untuk pesanan (Admin/Staff only).
     *
     * POST /api/v1/shipping/create-awb
     */
    public function createWaybill(ShippingCreateWaybillRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $order = Order::findOrFail($validated['order_id']);

        $shippingOrder = $this->shippingService->createWaybill(
            order: $order,
            courierCode: $validated['courier_code'],
            courierService: $validated['courier_service'],
            destinationPostalCode: $validated['destination_postal_code'],
            destinationAddress: $validated['destination_address'] ?? null
        );

        return ApiResponse::created($shippingOrder, 'Resi pengiriman ekspedisi berhasil dibuat.');
    }

    /**
     * Lacak riwayat pergerakan resi pengiriman (Publik / Pelanggan).
     *
     * GET /api/v1/shipping/track/{waybill_number}
     */
    public function track(string $waybillNumber): JsonResponse
    {
        $trackingData = $this->shippingService->trackWaybill($waybillNumber);

        if (! ($trackingData['found'] ?? false)) {
            return ApiResponse::error('Nomor resi tidak ditemukan dalam sistem.', null, 404);
        }

        return ApiResponse::success($trackingData, 'Data pelacakan resi berhasil ditemukan.');
    }
}
