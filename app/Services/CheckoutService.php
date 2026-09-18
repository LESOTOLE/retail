<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\ShippingOrder;
use App\Models\User;
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
     * lalu kurangi stok dalam satu transaksi database.
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

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => $user->id,
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
                    'origin_postal_code' => ShippingRateService::DEFAULT_ORIGIN_POSTAL_CODE,
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
                    $freshVariant = $variant->fresh();
                    if ($freshVariant && $freshVariant->needs_restock) {
                        event(new \App\Events\LowStockAlertEvent($freshVariant));
                    }
                }
            }

            $order = $order->load(['items.variant.product', 'user']);

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
     * Membatalkan order & mengembalikan stok yang sebelumnya dikunci.
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
            }

            $order->forceFill([
                'payment_status' => $paymentStatus,
                'fulfillment_status' => FulfillmentStatus::Cancelled,
            ])->save();

            event(new \App\Events\OrderStatusUpdatedEvent($order));

            return $order;
        });
    }
}
