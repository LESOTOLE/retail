<?php

namespace App\Services\AI;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vehicle;

/**
 * Eksekutor tool/function calling untuk AI Assistant (PRD 4.3).
 *
 * Seluruh data yang dikembalikan berasal langsung dari database sehingga
 * AI tidak pernah mengarang SKU maupun harga (guardrail PRD 4.3).
 */
class ProductToolExecutor
{
    /**
     * Definisi tool dalam format Gemini functionDeclarations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'search_products',
                'description' => 'Mencari produk suku cadang/aksesori pada katalog MotoVault. Gunakan vehicle_id bila pengguna menyebutkan motornya agar hasil hanya berisi produk yang kompatibel.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Kata kunci produk, misal "oli sintetik" atau "busi iridium".'],
                        'vehicle_id' => ['type' => 'integer', 'description' => 'ID kendaraan dari master data untuk filter kompatibilitas.'],
                        'category' => ['type' => 'string', 'description' => 'Slug atau nama kategori, misal "oli-cairan", "busi", "helm".'],
                        'max_price' => ['type' => 'number', 'description' => 'Batas harga maksimum dalam Rupiah.'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'check_compatibility',
                'description' => 'Memeriksa apakah sebuah SKU cocok dipasang pada kendaraan tertentu, beserta catatan pemasangan.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'sku' => ['type' => 'string', 'description' => 'Kode SKU varian produk.'],
                        'vehicle_id' => ['type' => 'integer', 'description' => 'ID kendaraan yang ingin dicek.'],
                    ],
                    'required' => ['sku', 'vehicle_id'],
                ],
            ],
            [
                'name' => 'get_product_detail',
                'description' => 'Mengambil detail lengkap sebuah produk berdasarkan SKU varian: harga final, stok, dan daftar kendaraan yang kompatibel.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'sku' => ['type' => 'string', 'description' => 'Kode SKU varian produk.'],
                    ],
                    'required' => ['sku'],
                ],
            ],
            [
                'name' => 'diagnose_symptom',
                'description' => 'Mendiagnosa keluhan atau gejala masalah motor (misal: "motor gredek pas tanjakan", "stang berat", "rem bunyi berdecit") dan merekomendasikan komponen/suku cadang terkait.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'complaint' => ['type' => 'string', 'description' => 'Deskripsi keluhan atau gejala motor dari pengguna.'],
                        'vehicle_id' => ['type' => 'integer', 'description' => 'ID kendaraan motor.'],
                    ],
                    'required' => ['complaint'],
                ],
            ],
            [
                'name' => 'check_shipping_rates',
                'description' => 'Mengecek ongkir / tarif pengiriman kurir (JNE, J&T, SiCepat, GoSend) ke kodepos atau kota tujuan pelanggan.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'destination_postal_code' => ['type' => 'string', 'description' => 'Kodepos tujuan pengiriman (5 digit), misal: "40123" (Bandung), "60111" (Surabaya), "12190" (Jakarta).'],
                        'courier' => ['type' => 'string', 'description' => 'Pilihan kurir jika spesifik (jne, jnt, sicepat, gosend).'],
                    ],
                    'required' => ['destination_postal_code'],
                ],
            ],
        ];
    }

    /**
     * Menjalankan tool berdasarkan nama.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, array $arguments): array
    {
        return match ($name) {
            'search_products' => $this->searchProducts(
                query: $arguments['query'] ?? null,
                vehicleId: isset($arguments['vehicle_id']) ? (int) $arguments['vehicle_id'] : null,
                category: $arguments['category'] ?? null,
                maxPrice: isset($arguments['max_price']) ? (float) $arguments['max_price'] : null,
            ),
            'check_compatibility' => $this->checkCompatibility(
                sku: (string) ($arguments['sku'] ?? ''),
                vehicleId: (int) ($arguments['vehicle_id'] ?? 0),
            ),
            'get_product_detail' => $this->getProductDetail((string) ($arguments['sku'] ?? '')),
            'diagnose_symptom' => app(DiagnosticRagService::class)->diagnose(
                complaint: (string) ($arguments['complaint'] ?? ''),
                vehicleId: isset($arguments['vehicle_id']) ? (int) $arguments['vehicle_id'] : null,
            ),
            'check_shipping_rates' => app(\App\Services\Shipping\ShippingRateService::class)->calculateRates(
                destinationPostalCode: (string) ($arguments['destination_postal_code'] ?? '12190'),
                courierFilter: $arguments['courier'] ?? null,
            ),
            default => ['error' => "Tool {$name} tidak dikenali."],
        };
    }

    /**
     * Tool: search_products(query, vehicle_id, category, max_price).
     *
     * @return array<string, mixed>
     */
    public function searchProducts(
        ?string $query = null,
        ?int $vehicleId = null,
        ?string $category = null,
        ?float $maxPrice = null,
        int $limit = 8
    ): array {
        $products = Product::query()
            ->active()
            ->with(['category:id,name,slug', 'variants', 'vehicles'])
            ->search($query)
            ->forVehicle($vehicleId)
            ->forCategory($category)
            ->priceBetween(null, $maxPrice)
            ->orderBy('base_price')
            ->limit($limit)
            ->get();

        $vehicle = $vehicleId ? Vehicle::find($vehicleId) : null;

        $results = $products->flatMap(
            fn (Product $product) => $this->mapProductVariants($product, $vehicle)
        )->values()->all();

        return [
            'vehicle_context' => $vehicle?->full_name,
            'result_count' => count($results),
            'products' => $results,
            'note' => $results === []
                ? 'Tidak ada produk yang cocok dengan kriteria tersebut di database. Jangan mengarang produk.'
                : 'Gunakan hanya SKU dan harga dari daftar ini.',
        ];
    }

    /**
     * Tool: check_compatibility(sku, vehicle_id).
     *
     * @return array<string, mixed>
     */
    public function checkCompatibility(string $sku, int $vehicleId): array
    {
        $variant = $this->findVariant($sku);
        $vehicle = Vehicle::find($vehicleId);

        if (! $variant) {
            return ['compatible' => false, 'reason' => "SKU {$sku} tidak ditemukan di database."];
        }

        if (! $vehicle) {
            return ['compatible' => false, 'reason' => "Kendaraan dengan id {$vehicleId} tidak ditemukan."];
        }

        $match = $variant->product->vehicles->firstWhere('id', $vehicle->id);

        if (! $match) {
            $alternatives = $this->alternativesForVehicle($vehicle, $variant->product->category_id);

            return [
                'compatible' => false,
                'sku' => $variant->sku,
                'vehicle' => $vehicle->full_name,
                'reason' => "{$variant->product->name} ({$variant->variant_name}) tidak terdaftar kompatibel dengan {$vehicle->full_name}.",
                'alternatives' => $alternatives,
            ];
        }

        return [
            'compatible' => true,
            'sku' => $variant->sku,
            'product_name' => $variant->product->name,
            'variant_name' => $variant->variant_name,
            'vehicle' => $vehicle->full_name,
            'installation_note' => $match->pivot->notes,
            'price' => $variant->final_price,
            'stock' => $variant->stock,
        ];
    }

    /**
     * Tool: get_product_detail(sku).
     *
     * @return array<string, mixed>
     */
    public function getProductDetail(string $sku): array
    {
        $variant = $this->findVariant($sku);

        if (! $variant) {
            return ['found' => false, 'reason' => "SKU {$sku} tidak ditemukan di database."];
        }

        $product = $variant->product;

        return [
            'found' => true,
            'sku' => $variant->sku,
            'name' => $product->name,
            'brand' => $product->brand,
            'category' => $product->category?->name,
            'description' => $product->description,
            'variant_name' => $variant->variant_name,
            'price' => $variant->final_price,
            'stock' => $variant->stock,
            'in_stock' => $variant->in_stock,
            'other_variants' => $product->variants
                ->where('id', '!=', $variant->id)
                ->map(fn (ProductVariant $v) => [
                    'sku' => $v->sku,
                    'variant_name' => $v->variant_name,
                    'price' => $v->final_price,
                    'stock' => $v->stock,
                ])->values()->all(),
            'compatible_vehicles' => $product->vehicles
                ->map(fn (Vehicle $v) => [
                    'vehicle_id' => $v->id,
                    'name' => $v->full_name,
                    'note' => $v->pivot->notes,
                ])->values()->all(),
        ];
    }

    /**
     * Memetakan seluruh varian produk menjadi baris rekomendasi.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function mapProductVariants(Product $product, ?Vehicle $vehicle): array
    {
        $note = $vehicle
            ? $product->vehicles->firstWhere('id', $vehicle->id)?->pivot->notes
            : null;

        return $product->variants->map(fn (ProductVariant $variant) => [
            'sku' => $variant->sku,
            'name' => $product->name.' - '.$variant->variant_name,
            'brand' => $product->brand,
            'category' => $product->category?->name,
            'price' => $variant->final_price,
            'stock' => $variant->stock,
            'in_stock' => $variant->in_stock,
            'compatibility_note' => $note,
        ])->all();
    }

    /**
     * Mencari varian berdasarkan SKU (case-insensitive) beserta relasinya.
     */
    protected function findVariant(string $sku): ?ProductVariant
    {
        return ProductVariant::query()
            ->with(['product.category', 'product.vehicles', 'product.variants'])
            ->whereRaw('UPPER(sku) = ?', [strtoupper(trim($sku))])
            ->first();
    }

    /**
     * Alternatif valid pada kategori yang sama untuk kendaraan tertentu
     * (guardrail PRD 4.3: arahkan ke varian alternatif yang valid).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function alternativesForVehicle(Vehicle $vehicle, int $categoryId, int $limit = 5): array
    {
        return Product::query()
            ->active()
            ->with(['variants', 'category:id,name,slug', 'vehicles'])
            ->where('category_id', $categoryId)
            ->forVehicle($vehicle->id)
            ->limit($limit)
            ->get()
            ->flatMap(fn (Product $product) => $this->mapProductVariants($product, $vehicle))
            ->values()
            ->all();
    }
}
