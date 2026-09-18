<?php

namespace App\Enums;

/**
 * Status pembayaran order sesuai ERD PRD bagian 5 (orders.payment_status).
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Expired = 'expired';
    case Failed = 'failed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Expired, self::Failed], true);
    }
}
