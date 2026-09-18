<?php

namespace App\Services\Warehouse;

use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service Proximity Routing & Penentuan Gudang Terdekat (PRD Phase 2 - Feature 5).
 *
 * Menggunakan rumus Haversine Geodesic untuk memilih gudang terdekat yang
 * memiliki ketersediaan stok lengkap untuk seluruh item keranjang belanja.
 */
class WarehouseRoutingService
{
    /**
     * Cari gudang terdekat yang memiliki seluruh item yang diminta.
     *
     * @param  float  $userLat  Latitude pelanggan
     * @param  float  $userLng  Longitude pelanggan
     * @param  array<int, array{variant_id?: int, sku?: string, quantity: int}>  $cartItems
     * @return array<string, mixed>
     */
    public function routeNearestWarehouse(float $userLat, float $userLng, array $cartItems): array
    {
        // Standarisasi items ke format [variant_id => quantity]
        $normalizedItems = $this->normalizeCartItems($cartItems);

        /** @var Collection<int, Warehouse> $warehouses */
        $warehouses = Warehouse::query()->active()->get();

        if ($warehouses->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Tidak ada gudang aktif yang terdaftar.',
                'warehouse' => null,
            ];
        }

        // Hitung jarak ke setiap gudang dan cek ketersediaan stok
        $evaluatedWarehouses = $warehouses->map(function (Warehouse $wh) use ($userLat, $userLng, $normalizedItems) {
            $distanceKm = $wh->calculateDistanceTo($userLat, $userLng);
            $stockCheck = $this->checkWarehouseStock($wh, $normalizedItems);

            return [
                'warehouse' => $wh,
                'distance_km' => $distanceKm,
                'has_full_stock' => $stockCheck['has_full_stock'],
                'available_items' => $stockCheck['available_items'],
                'missing_items' => $stockCheck['missing_items'],
            ];
        })->sortBy('distance_km')->values();

        // 1. Cari gudang terdekat yang memiliki full stock
        $bestMatch = $evaluatedWarehouses->firstWhere('has_full_stock', true);

        if ($bestMatch) {
            return [
                'success' => true,
                'matched' => true,
                'routing_strategy' => 'nearest_full_stock',
                'warehouse' => [
                    'id' => $bestMatch['warehouse']->id,
                    'code' => $bestMatch['warehouse']->code,
                    'name' => $bestMatch['warehouse']->name,
                    'city' => $bestMatch['warehouse']->city,
                    'address' => $bestMatch['warehouse']->address,
                    'distance_km' => $bestMatch['distance_km'],
                    'is_central' => $bestMatch['warehouse']->is_central,
                ],
                'alternatives' => $evaluatedWarehouses->all(),
            ];
        }

        // 2. Jika tidak ada yang full stock, arahkan ke Central Warehouse sebagai fallback
        $centralWh = $evaluatedWarehouses->first(fn ($item) => $item['warehouse']->is_central)
            ?? $evaluatedWarehouses->first();

        return [
            'success' => true,
            'matched' => false,
            'routing_strategy' => 'central_warehouse_fallback',
            'warehouse' => [
                'id' => $centralWh['warehouse']->id,
                'code' => $centralWh['warehouse']->code,
                'name' => $centralWh['warehouse']->name,
                'city' => $centralWh['warehouse']->city,
                'address' => $centralWh['warehouse']->address,
                'distance_km' => $centralWh['distance_km'],
                'is_central' => $centralWh['warehouse']->is_central,
            ],
            'note' => 'Gudang terdekat belum memiliki semua stok varian; dialihkan ke gudang pusat.',
            'alternatives' => $evaluatedWarehouses->all(),
        ];
    }

    /**
     * Mengurutkan seluruh cabang gudang berdasarkan jarak terdekat dari koordinat pengguna.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getWarehousesByProximity(float $userLat, float $userLng): array
    {
        return Warehouse::query()
            ->active()
            ->get()
            ->map(function (Warehouse $wh) use ($userLat, $userLng) {
                return [
                    'id' => $wh->id,
                    'code' => $wh->code,
                    'name' => $wh->name,
                    'city' => $wh->city,
                    'postal_code' => $wh->postal_code,
                    'address' => $wh->address,
                    'latitude' => $wh->latitude,
                    'longitude' => $wh->longitude,
                    'is_central' => $wh->is_central,
                    'distance_km' => $wh->calculateDistanceTo($userLat, $userLng),
                ];
            })
            ->sortBy('distance_km')
            ->values()
            ->all();
    }

    /**
     * Validasi stok varian pada gudang tertentu.
     *
     * @param  array<int, int>  $items  [variant_id => quantity]
     * @return array{has_full_stock: bool, available_items: array<int, mixed>, missing_items: array<int, mixed>}
     */
    public function checkWarehouseStock(Warehouse $warehouse, array $items): array
    {
        $hasFullStock = true;
        $available = [];
        $missing = [];

        if (empty($items)) {
            return [
                'has_full_stock' => true,
                'available_items' => [],
                'missing_items' => [],
            ];
        }

        $variantIds = array_keys($items);
        $warehouseStocks = WarehouseStock::query()
            ->with('variant.product')
            ->where('warehouse_id', $warehouse->id)
            ->whereIn('product_variant_id', $variantIds)
            ->get()
            ->keyBy('product_variant_id');

        foreach ($items as $variantId => $requiredQty) {
            /** @var WarehouseStock|null $whStock */
            $whStock = $warehouseStocks->get($variantId);
            $availableQty = $whStock ? $whStock->stock : 0;

            $itemInfo = [
                'variant_id' => $variantId,
                'sku' => $whStock?->variant?->sku,
                'required_quantity' => $requiredQty,
                'available_quantity' => $availableQty,
            ];

            if ($availableQty >= $requiredQty) {
                $available[] = $itemInfo;
            } else {
                $hasFullStock = false;
                $missing[] = $itemInfo;
            }
        }

        return [
            'has_full_stock' => $hasFullStock,
            'available_items' => $available,
            'missing_items' => $missing,
        ];
    }

    /**
     * Normalisasi payload cart items ke array [variant_id => qty].
     *
     * @param  array<int, array<string, mixed>>  $cartItems
     * @return array<int, int>
     */
    protected function normalizeCartItems(array $cartItems): array
    {
        $normalized = [];

        foreach ($cartItems as $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $variantId = $item['variant_id'] ?? null;

            if (! $variantId && ! empty($item['sku'])) {
                $variant = ProductVariant::where('sku', $item['sku'])->first();
                $variantId = $variant?->id;
            }

            if ($variantId) {
                $normalized[(int) $variantId] = ($normalized[(int) $variantId] ?? 0) + $qty;
            }
        }

        return $normalized;
    }
}
