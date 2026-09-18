<?php

namespace App\Services\Warehouse;

use App\Models\ProductVariant;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service Mutasi & Transfer Stok Antar Gudang Cabang (PRD Phase 2 - Feature 5).
 */
class StockTransferService
{
    /**
     * Buat draft mutasi transfer stok antar cabang.
     *
     * @param  array<int, array{variant_id: int, quantity: int}>  $items
     */
    public function createTransfer(
        Warehouse $fromWarehouse,
        Warehouse $toWarehouse,
        array $items,
        User $creator,
        ?string $notes = null
    ): StockTransfer {
        if ($fromWarehouse->id === $toWarehouse->id) {
            throw ValidationException::withMessages([
                'to_warehouse_id' => 'Gudang asal dan gudang tujuan tidak boleh sama.',
            ]);
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'Item transfer stok tidak boleh kosong.',
            ]);
        }

        return DB::transaction(function () use ($fromWarehouse, $toWarehouse, $items, $creator, $notes): StockTransfer {
            // Validasi ketersediaan stok di gudang asal
            foreach ($items as $item) {
                $variantId = (int) $item['variant_id'];
                $qty = (int) $item['quantity'];

                $whStock = WarehouseStock::where('warehouse_id', $fromWarehouse->id)
                    ->where('product_variant_id', $variantId)
                    ->first();

                $available = $whStock ? $whStock->stock : 0;

                if ($available < $qty) {
                    $variant = ProductVariant::find($variantId);
                    $sku = $variant?->sku ?? "ID #{$variantId}";
                    throw ValidationException::withMessages([
                        'items' => "Stok SKU {$sku} di gudang {$fromWarehouse->name} tidak mencukupi (Tersedia: {$available}, Diminta: {$qty}).",
                    ]);
                }
            }

            $prefix = 'TRF-' . now()->format('Ymd') . '-';
            $count = StockTransfer::whereDate('created_at', now()->toDateString())->count() + 1;
            $transferNumber = $prefix . str_pad((string) $count, 3, '0', STR_PAD_LEFT);

            /** @var StockTransfer $transfer */
            $transfer = StockTransfer::create([
                'transfer_number' => $transferNumber,
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'status' => 'pending',
                'notes' => $notes,
                'created_by' => $creator->id,
            ]);

            foreach ($items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_variant_id' => (int) $item['variant_id'],
                    'quantity' => (int) $item['quantity'],
                ]);
            }

            return $transfer->load(['fromWarehouse', 'toWarehouse', 'items.variant', 'creator']);
        });
    }

    /**
     * Kirim mutasi stok (status: in_transit) & kurangi stok dari gudang asal.
     */
    public function dispatchTransfer(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => "Transfer dengan status '{$transfer->status}' tidak dapat dikirim.",
            ]);
        }

        return DB::transaction(function () use ($transfer): StockTransfer {
            $transfer->loadMissing('items');

            foreach ($transfer->items as $item) {
                $whStock = WarehouseStock::where('warehouse_id', $transfer->from_warehouse_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($whStock->stock < $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => "Stok varian #{$item->product_variant_id} tidak mencukupi untuk dikirim.",
                    ]);
                }

                $whStock->decrement('stock', $item->quantity);
            }

            $transfer->update([
                'status' => 'in_transit',
                'transferred_at' => now(),
            ]);

            return $transfer->load(['fromWarehouse', 'toWarehouse', 'items.variant']);
        });
    }

    /**
     * Konfirmasi penerimaan mutasi stok (status: completed) & tambahkan stok di gudang tujuan.
     */
    public function completeTransfer(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->status !== 'in_transit') {
            throw ValidationException::withMessages([
                'status' => "Transfer hanya dapat diselesaikan jika berstatus 'in_transit'.",
            ]);
        }

        return DB::transaction(function () use ($transfer): StockTransfer {
            $transfer->loadMissing('items');

            foreach ($transfer->items as $item) {
                $targetStock = WarehouseStock::firstOrCreate(
                    [
                        'warehouse_id' => $transfer->to_warehouse_id,
                        'product_variant_id' => $item->product_variant_id,
                    ],
                    [
                        'stock' => 0,
                        'min_stock_alert' => 3,
                    ]
                );

                $targetStock->increment('stock', $item->quantity);
            }

            $transfer->update([
                'status' => 'completed',
                'received_at' => now(),
            ]);

            return $transfer->load(['fromWarehouse', 'toWarehouse', 'items.variant']);
        });
    }

    /**
     * Batalkan transfer mutasi stok.
     */
    public function cancelTransfer(StockTransfer $transfer): StockTransfer
    {
        if (in_array($transfer->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => "Transfer yang sudah '{$transfer->status}' tidak dapat dibatalkan.",
            ]);
        }

        return DB::transaction(function () use ($transfer): StockTransfer {
            // Jika sudah in_transit, kembalikan stok ke gudang asal
            if ($transfer->status === 'in_transit') {
                $transfer->loadMissing('items');

                foreach ($transfer->items as $item) {
                    $sourceStock = WarehouseStock::where('warehouse_id', $transfer->from_warehouse_id)
                        ->where('product_variant_id', $item->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if ($sourceStock) {
                        $sourceStock->increment('stock', $item->quantity);
                    }
                }
            }

            $transfer->update([
                'status' => 'cancelled',
            ]);

            return $transfer->load(['fromWarehouse', 'toWarehouse', 'items.variant']);
        });
    }
}
