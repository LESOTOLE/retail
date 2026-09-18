<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Validasi ketersediaan stok real-time (PRD 4.4 & endpoint /cart/validate).
 */
class InventoryService
{
    /**
     * Memvalidasi daftar item keranjang terhadap stok terkini.
     *
     * @param  array<string, int>  $items  [sku => quantity]
     * @param  bool  $lock  true untuk mengunci baris (SELECT ... FOR UPDATE)
     * @return array{valid: bool, total_amount: float, items: array<int, array<string, mixed>>, issues: array<int, array<string, mixed>>}
     */
    public function validateCart(array $items, bool $lock = false): array
    {
        $variants = $this->resolveVariants(array_keys($items), $lock);

        $lines = [];
        $issues = [];
        $total = 0.0;

        foreach ($items as $sku => $quantity) {
            /** @var ProductVariant|null $variant */
            $variant = $variants->get($sku);

            if (! $variant) {
                $issues[] = [
                    'sku' => $sku,
                    'reason' => 'not_found',
                    'message' => "SKU {$sku} tidak ditemukan pada katalog.",
                ];

                continue;
            }

            if (! $variant->product?->is_active) {
                $issues[] = [
                    'sku' => $sku,
                    'reason' => 'inactive',
                    'message' => "Produk untuk SKU {$sku} sedang tidak dijual.",
                ];

                continue;
            }

            $unitPrice = $variant->final_price;
            $available = $variant->hasStockFor($quantity);

            if (! $available) {
                $issues[] = [
                    'sku' => $sku,
                    'reason' => 'insufficient_stock',
                    'requested' => $quantity,
                    'available' => $variant->stock,
                    'message' => "Stok {$variant->product->name} ({$variant->variant_name}) tersisa {$variant->stock}, diminta {$quantity}.",
                ];
            }

            $subtotal = round($unitPrice * $quantity, 2);
            $total += $subtotal;

            $lines[] = [
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->variant_name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'stock' => $variant->stock,
                'available' => $available,
            ];
        }

        return [
            'valid' => $issues === [],
            'total_amount' => round($total, 2),
            'items' => $lines,
            'issues' => $issues,
        ];
    }

    /**
     * Mengambil varian beserta produknya, dikunci bila diperlukan.
     *
     * @param  array<int, string>  $skus
     * @return Collection<string, ProductVariant>
     */
    public function resolveVariants(array $skus, bool $lock = false): Collection
    {
        $query = ProductVariant::query()
            ->with('product:id,name,slug,base_price,is_active')
            ->whereIn('sku', $skus);

        if ($lock) {
            // Urutkan id agar konsisten & menghindari deadlock saat lock beruntun.
            $query->orderBy('id')->lockForUpdate();
        }

        return $query->get()->keyBy('sku');
    }

    /**
     * Mengurangi stok varian (dipanggil di dalam transaksi checkout).
     */
    public function decrementStock(ProductVariant $variant, int $quantity): void
    {
        $variant->decrement('stock', $quantity);
    }

    /**
     * Mengembalikan stok saat order dibatalkan / expired.
     */
    public function incrementStock(ProductVariant $variant, int $quantity): void
    {
        $variant->increment('stock', $quantity);
    }

    /**
     * Daftar varian yang memicu flag restock (PRD 4.2).
     *
     * @return Collection<int, ProductVariant>
     */
    public function lowStockVariants(): Collection
    {
        return ProductVariant::query()
            ->with('product:id,name,slug,base_price')
            ->lowStock()
            ->orderBy('stock')
            ->get();
    }
}
