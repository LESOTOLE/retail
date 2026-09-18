<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pesanan (PRD 5 - orders, PRD 4.4 workflow).
 *
 * @property int $id
 * @property string $order_number
 * @property int $user_id
 * @property string $total_amount
 * @property PaymentStatus $payment_status
 * @property FulfillmentStatus $fulfillment_status
 * @property string|null $tracking_number
 * @property string|null $payment_reference
 */
class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'total_amount',
        'payment_status',
        'fulfillment_status',
        'payment_method',
        'tracking_number',
        'payment_reference',
        'shipping_address',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Item pesanan; cascade delete mengikuti order.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Data ekspedisi & resi pengiriman (3PL Logistics).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<ShippingOrder, $this>
     */
    public function shippingOrder(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ShippingOrder::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function isCancellable(): bool
    {
        return $this->fulfillment_status->isCancellable()
            && $this->payment_status !== PaymentStatus::Paid;
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->when(
            ! $user->canManageStore(),
            fn (Builder $q) => $q->where('user_id', $user->id)
        );
    }

    /**
     * Membuat nomor order unik dengan format ORD-YYYYMMDD-XXX (PRD 5).
     */
    public static function generateOrderNumber(?\DateTimeInterface $date = null): string
    {
        $date ??= now();
        $prefix = 'ORD-'.$date->format('Ymd').'-';

        $lastNumber = static::query()
            ->where('order_number', 'like', $prefix.'%')
            ->orderByDesc('order_number')
            ->lockForUpdate()
            ->value('order_number');

        $sequence = $lastNumber
            ? ((int) substr($lastNumber, -3)) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
