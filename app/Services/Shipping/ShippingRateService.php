<?php

namespace App\Services\Shipping;

use App\Enums\FulfillmentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service Kalkulasi Ongkir Multi-Ekspedisi & Resi Otomatis (PRD Phase 2 - Feature 3).
 *
 * Mendukung Biteship API / RajaOngkir API dengan fallback rate engine multi-kurir:
 * JNE, J&T Express, SiCepat, dan GoSend Instant/SameDay.
 */
class ShippingRateService
{
    public const DEFAULT_ORIGIN_POSTAL_CODE = '12190'; // Jakarta Selatan Hub

    /**
     * Hitung ongkos kirim multi-kurir berdasarkan origin, destination, dan item produk.
     *
     * @param  array<int, array{variant_id?: int, product_id?: int, quantity?: int, weight_gram?: int, length_cm?: float, width_cm?: float, height_cm?: float}>  $items
     * @return array<string, mixed>
     */
    public function calculateRates(
        string $destinationPostalCode,
        array $items = [],
        string $originPostalCode = self::DEFAULT_ORIGIN_POSTAL_CODE,
        ?string $courierFilter = null
    ): array {
        $weightBreakdown = $this->computeWeightAndDimensions($items);
        $totalWeightKg = $weightBreakdown['chargeable_weight_kg'];

        $cacheKey = sprintf(
            'shipping_rates_%s_%s_%d_%s',
            $originPostalCode,
            $destinationPostalCode,
            (int) ($totalWeightKg * 1000),
            $courierFilter ?? 'all'
        );

        return Cache::remember($cacheKey, 3600, function () use ($originPostalCode, $destinationPostalCode, $totalWeightKg, $weightBreakdown, $courierFilter) {
            // Cek integrasi Biteship API jika API key tersedia
            $apiKey = config('services.biteship.api_key');
            if (filled($apiKey)) {
                $externalRates = $this->fetchFromBiteship($originPostalCode, $destinationPostalCode, $totalWeightKg, $courierFilter);
                if (! empty($externalRates)) {
                    return [
                        'origin_postal_code' => $originPostalCode,
                        'destination_postal_code' => $destinationPostalCode,
                        'weight_breakdown' => $weightBreakdown,
                        'source' => 'biteship_api',
                        'rates' => $externalRates,
                    ];
                }
            }

            // Fallback kalkulasi matriks lokal
            $localRates = $this->calculateLocalMatrixRates($originPostalCode, $destinationPostalCode, $totalWeightKg, $courierFilter);

            return [
                'origin_postal_code' => $originPostalCode,
                'destination_postal_code' => $destinationPostalCode,
                'weight_breakdown' => $weightBreakdown,
                'source' => 'local_matrix_engine',
                'rates' => $localRates,
            ];
        });
    }

    /**
     * Hitung total berat nyata dan berat volumetrik.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function computeWeightAndDimensions(array $items): array
    {
        $totalActualGrams = 0;
        $totalVolumetricGrams = 0;
        $itemDetails = [];

        foreach ($items as $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $weightGram = $item['weight_gram'] ?? null;
            $length = (float) ($item['length_cm'] ?? 0);
            $width = (float) ($item['width_cm'] ?? 0);
            $height = (float) ($item['height_cm'] ?? 0);

            if ($weightGram === null) {
                if (! empty($item['variant_id'])) {
                    $variant = ProductVariant::with('product')->find($item['variant_id']);
                    if ($variant) {
                        $weightGram = $variant->weight_gram ?? $variant->product?->weight_gram ?? 250;
                        $length = $length ?: (float) ($variant->length_cm ?? $variant->product?->length_cm ?? 15);
                        $width = $width ?: (float) ($variant->width_cm ?? $variant->product?->width_cm ?? 10);
                        $height = $height ?: (float) ($variant->height_cm ?? $variant->product?->height_cm ?? 5);
                    }
                } elseif (! empty($item['product_id'])) {
                    $product = Product::find($item['product_id']);
                    if ($product) {
                        $weightGram = $product->weight_gram ?? 250;
                        $length = $length ?: (float) ($product->length_cm ?? 15);
                        $width = $width ?: (float) ($product->width_cm ?? 10);
                        $height = $height ?: (float) ($product->height_cm ?? 5);
                    }
                }
            }

            $weightGram = (int) ($weightGram ?? 250);
            $itemActualGrams = $weightGram * $qty;
            $totalActualGrams += $itemActualGrams;

            // Volumetrik = (P x L x T / 6000) kg -> gram
            $itemVolumetricKg = ($length * $width * $height) / 6000;
            $itemVolumetricGrams = (int) round($itemVolumetricKg * 1000 * $qty);
            $totalVolumetricGrams += $itemVolumetricGrams;

            $itemDetails[] = [
                'variant_id' => $item['variant_id'] ?? null,
                'product_id' => $item['product_id'] ?? null,
                'quantity' => $qty,
                'unit_weight_gram' => $weightGram,
                'actual_weight_gram' => $itemActualGrams,
                'volumetric_weight_gram' => $itemVolumetricGrams,
            ];
        }

        $totalActualGrams = max(250, $totalActualGrams);
        $chargeableGrams = max($totalActualGrams, $totalVolumetricGrams);
        $chargeableWeightKg = max(1.0, round($chargeableGrams / 1000, 2));

        return [
            'actual_weight_gram' => $totalActualGrams,
            'volumetric_weight_gram' => $totalVolumetricGrams,
            'chargeable_weight_gram' => $chargeableGrams,
            'chargeable_weight_kg' => $chargeableWeightKg,
            'items' => $itemDetails,
        ];
    }

    /**
     * Buat Resi Pengiriman (AWB) dan pasangkan ke Order.
     */
    public function createWaybill(
        Order $order,
        string $courierCode,
        string $courierService,
        string $destinationPostalCode,
        ?string $destinationAddress = null
    ): ShippingOrder {
        $rates = $this->calculateRates($destinationPostalCode, [], self::DEFAULT_ORIGIN_POSTAL_CODE, $courierCode);
        
        $selectedRate = collect($rates['rates'])->first(function ($r) use ($courierCode, $courierService) {
            return strtolower($r['courier_code']) === strtolower($courierCode)
                && strtolower($r['service_code']) === strtolower($courierService);
        });

        $shippingCost = $selectedRate ? (float) $selectedRate['price'] : 15000.00;
        $insuranceCost = $selectedRate ? (float) ($selectedRate['insurance_cost'] ?? 0) : 0.00;

        $prefix = strtoupper($courierCode);
        $waybillNumber = sprintf('MV-%s-%s-%s', $prefix, now()->format('ymd'), strtoupper(Str::random(6)));

        $initialHistory = [
            [
                'status' => 'ready_to_ship',
                'description' => 'Pesanan siap diserahkan ke kurir ' . strtoupper($courierCode),
                'location' => 'Jakarta Selatan Warehouse',
                'timestamp' => now()->toIso8601String(),
            ],
        ];

        /** @var ShippingOrder $shippingOrder */
        $shippingOrder = ShippingOrder::updateOrCreate(
            ['order_id' => $order->id],
            [
                'courier_code' => strtolower($courierCode),
                'courier_service' => strtoupper($courierService),
                'waybill_number' => $waybillNumber,
                'tracking_status' => 'ready_to_ship',
                'shipping_cost' => $shippingCost,
                'insurance_cost' => $insuranceCost,
                'origin_postal_code' => self::DEFAULT_ORIGIN_POSTAL_CODE,
                'destination_postal_code' => $destinationPostalCode,
                'destination_address' => $destinationAddress ?? $order->shipping_address,
                'raw_tracking_history' => $initialHistory,
            ]
        );

        $order->update([
            'tracking_number' => $waybillNumber,
            'fulfillment_status' => FulfillmentStatus::Shipped,
        ]);

        event(new \App\Events\OrderStatusUpdatedEvent($order));

        return $shippingOrder;
    }

    /**
     * Lacak riwayat pergerakan resi pengiriman.
     *
     * @return array<string, mixed>
     */
    public function trackWaybill(string $waybillNumber): array
    {
        $shippingOrder = ShippingOrder::with('order')->where('waybill_number', $waybillNumber)->first();

        if (! $shippingOrder) {
            return [
                'found' => false,
                'waybill_number' => $waybillNumber,
                'message' => 'Nomor resi tidak ditemukan dalam sistem.',
            ];
        }

        return [
            'found' => true,
            'order_number' => $shippingOrder->order?->order_number,
            'courier_code' => $shippingOrder->courier_code,
            'courier_service' => $shippingOrder->courier_service,
            'waybill_number' => $shippingOrder->waybill_number,
            'tracking_status' => $shippingOrder->tracking_status,
            'origin_postal_code' => $shippingOrder->origin_postal_code,
            'destination_postal_code' => $shippingOrder->destination_postal_code,
            'destination_address' => $shippingOrder->destination_address,
            'shipping_cost' => (float) $shippingOrder->shipping_cost,
            'history' => $shippingOrder->raw_tracking_history ?? [],
            'updated_at' => $shippingOrder->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Hitung ongkos kirim menggunakan matriks lokal logistik MotoVault.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function calculateLocalMatrixRates(
        string $originPostal,
        string $destinationPostal,
        float $weightKg,
        ?string $courierFilter = null
    ): array {
        // Multiplier zona berdasarkan kesamaan digit kodepos
        $zoneMultiplier = 1.0;
        $originPrefix = substr($originPostal, 0, 2);
        $destPrefix = substr($destinationPostal, 0, 2);

        $isSameArea = ($originPrefix === $destPrefix);
        $isSameIsland = (substr($originPostal, 0, 1) === substr($destinationPostal, 0, 1));

        if ($isSameArea) {
            $zoneMultiplier = 1.0; // Jabodetabek / dalam kota
        } elseif ($isSameIsland) {
            $zoneMultiplier = 1.4; // Pulau Jawa
        } else {
            $zoneMultiplier = 2.5; // Luar Pulau Jawa
        }

        $allCouriers = [
            // JNE
            [
                'courier_code' => 'jne',
                'courier_name' => 'JNE Express',
                'service_code' => 'REG',
                'service_name' => 'Layanan Reguler',
                'base_rate_per_kg' => 10000,
                'etd_days' => $isSameArea ? '1-2' : ($isSameIsland ? '2-3' : '3-5'),
                'is_instant' => false,
            ],
            [
                'courier_code' => 'jne',
                'courier_name' => 'JNE Express',
                'service_code' => 'YES',
                'service_name' => 'Yakin Esok Sampai',
                'base_rate_per_kg' => 19000,
                'etd_days' => '1',
                'is_instant' => false,
            ],
            [
                'courier_code' => 'jne',
                'courier_name' => 'JNE Express',
                'service_code' => 'OKE',
                'service_name' => 'Ongkos Kirim Ekonomis',
                'base_rate_per_kg' => 8000,
                'etd_days' => $isSameArea ? '2-3' : ($isSameIsland ? '3-5' : '5-7'),
                'is_instant' => false,
            ],
            // J&T
            [
                'courier_code' => 'jnt',
                'courier_name' => 'J&T Express',
                'service_code' => 'EZ',
                'service_name' => 'Reguler Service',
                'base_rate_per_kg' => 11000,
                'etd_days' => $isSameArea ? '1-2' : ($isSameIsland ? '2-3' : '3-5'),
                'is_instant' => false,
            ],
            [
                'courier_code' => 'jnt',
                'courier_name' => 'J&T Express',
                'service_code' => 'SUPER',
                'service_name' => 'J&T Super Express',
                'base_rate_per_kg' => 21000,
                'etd_days' => '1',
                'is_instant' => false,
            ],
            // SiCepat
            [
                'courier_code' => 'sicepat',
                'courier_name' => 'SiCepat Ekspres',
                'service_code' => 'SIUNT',
                'service_name' => 'SiUntung Reguler',
                'base_rate_per_kg' => 10500,
                'etd_days' => $isSameArea ? '1-2' : ($isSameIsland ? '2-3' : '3-5'),
                'is_instant' => false,
            ],
            [
                'courier_code' => 'sicepat',
                'courier_name' => 'SiCepat Ekspres',
                'service_code' => 'BEST',
                'service_name' => 'Besok Sampai Tujuan',
                'base_rate_per_kg' => 18500,
                'etd_days' => '1',
                'is_instant' => false,
            ],
            [
                'courier_code' => 'sicepat',
                'courier_name' => 'SiCepat Ekspres',
                'service_code' => 'GOKIL',
                'service_name' => 'Cargo Kilat (>5kg)',
                'base_rate_per_kg' => 6000,
                'etd_days' => '3-5',
                'min_weight_kg' => 5.0,
                'is_instant' => false,
            ],
        ];

        // GoSend hanya untuk area yang sama (Jabodetabek / sama prefix 2 digit)
        if ($isSameArea) {
            $allCouriers[] = [
                'courier_code' => 'gosend',
                'courier_name' => 'GoSend by Gojek',
                'service_code' => 'INSTANT',
                'service_name' => 'Instant Delivery (1-2 Jam)',
                'base_rate_per_kg' => 25000,
                'etd_days' => '0 (1-2 Jam)',
                'is_instant' => true,
            ];
            $allCouriers[] = [
                'courier_code' => 'gosend',
                'courier_name' => 'GoSend by Gojek',
                'service_code' => 'SAMEDAY',
                'service_name' => 'Same Day Delivery (6-8 Jam)',
                'base_rate_per_kg' => 16000,
                'etd_days' => '0 (6-8 Jam)',
                'is_instant' => true,
            ];
        }

        $rates = [];
        $billingWeight = max(1.0, ceil($weightKg));

        foreach ($allCouriers as $courier) {
            if ($courierFilter && strtolower($courier['courier_code']) !== strtolower($courierFilter)) {
                continue;
            }

            if (! empty($courier['min_weight_kg']) && $weightKg < $courier['min_weight_kg']) {
                continue;
            }

            $price = $courier['is_instant']
                ? $courier['base_rate_per_kg'] + (($billingWeight - 1) * 5000)
                : round($courier['base_rate_per_kg'] * $zoneMultiplier * $billingWeight);

            $rates[] = [
                'courier_code' => $courier['courier_code'],
                'courier_name' => $courier['courier_name'],
                'service_code' => $courier['service_code'],
                'service_name' => $courier['service_name'],
                'price' => (float) $price,
                'formatted_price' => 'Rp ' . number_format($price, 0, ',', '.'),
                'etd' => $courier['etd_days'] . ($courier['is_instant'] ? '' : ' Hari'),
                'is_instant' => $courier['is_instant'],
            ];
        }

        return $rates;
    }

    /**
     * Request tarif real ke Biteship API jika terkonfigurasi.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchFromBiteship(
        string $originPostal,
        string $destinationPostal,
        float $weightKg,
        ?string $courierFilter = null
    ): array {
        try {
            $apiKey = config('services.biteship.api_key');
            $baseUrl = config('services.biteship.base_url', 'https://api.biteship.com/v1');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->timeout(5)->post($baseUrl . '/rates/couriers', [
                'origin_postal_code' => (int) $originPostal,
                'destination_postal_code' => (int) $destinationPostal,
                'couriers' => $courierFilter ?? 'jne,jnt,sicepat,gosend',
                'items' => [
                    [
                        'name' => 'MotoVault Spareparts Package',
                        'value' => 100000,
                        'weight' => (int) ($weightKg * 1000),
                    ],
                ],
            ]);

            if ($response->successful()) {
                $pricing = $response->json('pricing', []);
                $rates = [];
                foreach ($pricing as $item) {
                    $rates[] = [
                        'courier_code' => strtolower($item['courier_code'] ?? 'courier'),
                        'courier_name' => $item['courier_name'] ?? 'Courier',
                        'service_code' => strtoupper($item['courier_service_code'] ?? 'REG'),
                        'service_name' => $item['courier_service_name'] ?? 'Service',
                        'price' => (float) ($item['price'] ?? 0),
                        'formatted_price' => 'Rp ' . number_format($item['price'] ?? 0, 0, ',', '.'),
                        'etd' => ($item['duration'] ?? '1-3') . ' Hari',
                        'is_instant' => ($item['type'] ?? '') === 'instant',
                    ];
                }

                return $rates;
            }
        } catch (\Throwable $e) {
            Log::warning('Biteship API call failed, falling back to local matrix: ' . $e->getMessage());
        }

        return [];
    }
}
