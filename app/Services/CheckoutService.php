<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\ShippingOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Shipping\ShippingRateService;
use Illuminate\Support\Facades\DB;

/**
 * Membuat invoice pesanan & mengunci stok secara atomik (PRD 4.4).
 */
class CheckoutService
{
    public function __construct(
        protected InventoryService $inventory
    ) {}

    /**
     * Proses checkout: validasi stok dengan row lock, buat order + items,
     * serta kurangi stok varian & stok gudang dalam satu transaksi database.
     *
     * @param  array<string, int>  $items  [sku => quantity]
     * @param  array<string, mixed>  $shippingData
     *
     * @throws InsufficientStockException
     */
    public function checkout(User $user, array $items, array $shippingData = []): Order
    {
        return DB::transaction(function () use ($user, $items, $shippingData): Order {
            $result = $this->inventory->validateCart($items, lock: true);

            if (! $result['valid']) {
                throw new InsufficientStockException($result['issues']);
            }

            $shippingCost = (float) ($shippingData['shipping_cost'] ?? 0);
            $totalAmount = (float) $result['total_amount'] + $shippingCost;

            // Resolusi Gudang Asal Pengiriman / Penjualan (Multi-Warehouse Proximity)
            $warehouseId = $shippingData['warehouse_id'] ?? null;
            $warehouse = null;
            if ($warehouseId) {
                $warehouse = Warehouse::find($warehouseId);
            }
            if (! $warehouse) {
                $warehouse = Warehouse::where('is_central', true)->first()
                    ?? Warehouse::where('is_active', true)->first();
            }

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => $user->id,
                'warehouse_id' => $warehouse?->id,
                'total_amount' => $totalAmount,
                'payment_status' => PaymentStatus::Unpaid,
                'fulfillment_status' => FulfillmentStatus::Pending,
                'payment_method' => $shippingData['payment_method'] ?? null,
                'shipping_address' => $shippingData['shipping_address'] ?? null,
            ]);

            if (! empty($shippingData['courier_code'])) {
                ShippingOrder::create([
                    'order_id' => $order->id,
                    'courier_code' => $shippingData['courier_code'],
                    'courier_service' => $shippingData['courier_service'] ?? 'REG',
                    'shipping_cost' => $shippingCost,
                    'origin_postal_code' => $warehouse?->postal_code ?? ShippingRateService::DEFAULT_ORIGIN_POSTAL_CODE,
                    'destination_postal_code' => $shippingData['destination_postal_code'] ?? '00000',
                    'destination_address' => $shippingData['shipping_address'] ?? null,
                    'tracking_status' => 'PENDING',
                ]);
            }

            $variants = $this->inventory->resolveVariants(array_keys($items));

            foreach ($result['items'] as $line) {
                $order->items()->create([
                    'product_variant_id' => $line['variant_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $line['subtotal'],
                ]);

                $variant = $variants->get($line['sku']);

                if ($variant) {
                    $this->inventory->decrementStock($variant, $line['quantity']);

                    // Sinkronisasi stok spesifik cabang gudang (WarehouseStock)
                    if ($warehouse) {
                        $whStock = WarehouseStock::where('warehouse_id', $warehouse->id)
                            ->where('product_variant_id', $variant->id)
                            ->lockForUpdate()
                            ->first();

                        if ($whStock) {
                            $whStock->decrement('stock', $line['quantity']);
                        }
                    }

                    $freshVariant = $variant->fresh();
                    if ($freshVariant && $freshVariant->needs_restock) {
                        event(new \App\Events\LowStockAlertEvent($freshVariant));
                    }
                }
            }

            $order = $order->load(['items.variant.product', 'user', 'warehouse']);

            event(new \App\Events\OrderCreatedEvent($order));

            return $order;
        });
    }

    /**
     * Menandai order sebagai lunas dan memindahkan fulfillment ke processing
     * (alur pending -> paid -> processing pada PRD 4.4).
     */
    public function markAsPaid(Order $order, ?string $paymentReference = null): Order
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return $order;
        }

        $order->forceFill([
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => $order->fulfillment_status === FulfillmentStatus::Pending
                ? FulfillmentStatus::Processing
                : $order->fulfillment_status,
            'payment_reference' => $paymentReference ?? $order->payment_reference,
            'paid_at' => now(),
        ])->save();

        event(new \App\Events\OrderStatusUpdatedEvent($order));

        return $order;
    }

    /**
     * Membatalkan order & mengembalikan stok varian dan stok gudang yang sebelumnya dikunci.
     */
    public function cancel(Order $order, PaymentStatus $paymentStatus = PaymentStatus::Failed): Order
    {
        return DB::transaction(function () use ($order, $paymentStatus): Order {
            if ($order->fulfillment_status === FulfillmentStatus::Cancelled) {
                return $order;
            }

            $order->loadMissing('items.variant');

            foreach ($order->items as $item) {
                if ($item->variant) {
                    $this->inventory->incrementStock($item->variant, $item->quantity);
                }

                // Pulihkan stok cabang gudang asal jika ada
                if ($order->warehouse_id && $item->product_variant_id) {
                    $whStock = WarehouseStock::where('warehouse_id', $order->warehouse_id)
                        ->where('product_variant_id', $item->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if ($whStock) {
                        $whStock->increment('stock', $item->quantity);
                    }
                }
            }

            $order->forceFill([
                'payment_status' => $paymentStatus,
                'fulfillment_status' => FulfillmentStatus::Cancelled,
            ])->save();

            event(new \App\Events\OrderStatusUpdatedEvent($order));

            return $order;
        });
    }

    /**
     * Membatalkan seluruh pesanan berstatus unpaid yang melebihi batas waktu toleransi (TTL 30 menit)
     * dan mengembalikan kuantitas stok varian secara atomik (PRD 4.2).
     *
     * @return int Jumlah pesanan yang berhasil dibatalkan
     */
    public function cancelExpiredOrders(int $ttlMinutes = 30): int
    {
        $threshold = now()->subMinutes($ttlMinutes);

        $expiredOrders = Order::query()
            ->where('payment_status', PaymentStatus::Unpaid)
            ->where('fulfillment_status', '!=', FulfillmentStatus::Cancelled)
            ->where('created_at', '<=', $threshold)
            ->with('items.variant')
            ->get();

        $count = 0;
        foreach ($expiredOrders as $order) {
            $this->cancel($order, PaymentStatus::Expired);
            $count++;
        }

        return $count;
    }
}
