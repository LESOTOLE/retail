<?php

namespace App\Enums;

/**
 * Status pemenuhan pesanan sesuai ERD PRD bagian 5 (orders.fulfillment_status)
 * dan alur workflow PRD bagian 4.4.
 */
enum FulfillmentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Transisi status yang diizinkan (state machine sederhana).
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Order yang masih boleh dibatalkan (stok dikembalikan).
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }
}
