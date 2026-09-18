<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log callback payment gateway untuk menjamin idempotency (PRD 4.4).
 *
 * @property int $id
 * @property string $provider
 * @property string $event_key
 * @property string|null $order_number
 * @property string|null $status
 * @property array<string, mixed> $payload
 */
class PaymentWebhookLog extends Model
{
    protected $fillable = [
        'provider',
        'event_key',
        'order_number',
        'status',
        'payload',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function markProcessed(): void
    {
        $this->forceFill(['processed_at' => now()])->save();
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }
}
