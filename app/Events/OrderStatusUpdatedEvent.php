<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast saat status pembayaran / fulfillment pesanan berubah.
 */
class OrderStatusUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('orders.' . $this->order->order_number),
            new PrivateChannel('orders.staff'),
            new PrivateChannel('orders.user.' . $this->order->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderStatusUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'payment_status' => $this->order->payment_status?->value ?? (string) $this->order->payment_status,
            'fulfillment_status' => $this->order->fulfillment_status?->value ?? (string) $this->order->fulfillment_status,
            'tracking_number' => $this->order->tracking_number,
            'payment_reference' => $this->order->payment_reference,
            'paid_at' => $this->order->paid_at?->toIso8601String(),
            'updated_at' => $this->order->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
