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
 * Event broadcast saat order baru dibuat (PRD Phase 2 - Feature 4 WebSockets).
 *
 * Menghubungkan kasir/staff dan pelanggan via Real-Time WebSockets.
 */
class OrderCreatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    /**
     * Channel siaran WebSockets.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('orders'),
            new PrivateChannel('orders.staff'),
            new PrivateChannel('orders.user.' . $this->order->user_id),
        ];
    }

    /**
     * Nama event broadcast untuk client JS / Echo.
     */
    public function broadcastAs(): string
    {
        return 'OrderCreated';
    }

    /**
     * Data payload yang disiarkan ke client.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'customer_name' => $this->order->user?->name ?? 'Guest / POS Customer',
            'total_amount' => (float) $this->order->total_amount,
            'payment_status' => $this->order->payment_status?->value ?? (string) $this->order->payment_status,
            'fulfillment_status' => $this->order->fulfillment_status?->value ?? (string) $this->order->fulfillment_status,
            'payment_method' => $this->order->payment_method,
            'items_count' => $this->order->items()->count(),
            'created_at' => $this->order->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
