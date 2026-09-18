<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Pengiriman & Resi Ekspedisi (PRD Phase 2 - Feature 3).
 *
 * @property int $id
 * @property int $order_id
 * @property string $courier_code
 * @property string $courier_service
 * @property string|null $waybill_number
 * @property string $tracking_status
 * @property string $shipping_cost
 * @property string $insurance_cost
 * @property string $origin_postal_code
 * @property string $destination_postal_code
 * @property string|null $destination_address
 * @property array<string, mixed>|null $raw_tracking_history
 */
class ShippingOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'courier_code',
        'courier_service',
        'waybill_number',
        'tracking_status',
        'shipping_cost',
        'insurance_cost',
        'origin_postal_code',
        'destination_postal_code',
        'destination_address',
        'raw_tracking_history',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_cost' => 'decimal:2',
            'insurance_cost' => 'decimal:2',
            'raw_tracking_history' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
