<?php

namespace App\Events;

use App\Models\ProductVariant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast saat stok varian menipis <= min_stock_alert (PRD Phase 2 - Feature 4).
 */
class LowStockAlertEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ProductVariant $variant
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('inventory.alerts'),
            new PrivateChannel('inventory.staff'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'LowStockAlert';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'variant_id' => $this->variant->id,
            'sku' => $this->variant->sku,
            'product_name' => $this->variant->product?->name ?? 'Sparepart',
            'variant_name' => $this->variant->variant_name,
            'current_stock' => $this->variant->stock,
            'min_stock_alert' => $this->variant->min_stock_alert,
            'alert_message' => sprintf(
                'Perhatian! Stok SKU %s (%s - %s) tersisa %d unit (ambang batas: %d).',
                $this->variant->sku,
                $this->variant->product?->name,
                $this->variant->variant_name,
                $this->variant->stock,
                $this->variant->min_stock_alert
            ),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
