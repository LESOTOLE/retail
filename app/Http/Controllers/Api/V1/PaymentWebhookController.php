<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentWebhookLog;
use App\Services\CheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Endpoint Callback Payment Gateway yang Idempotent (PRD 4.4 & 6.6).
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout
    ) {}

    /**
     * POST /api/v1/webhooks/payment
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $provider = $request->header('X-Payment-Provider', 'midtrans');

        $orderNumber = $payload['order_id'] ?? $payload['order_number'] ?? $payload['external_id'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? $payload['status'] ?? null;
        $transactionId = $payload['transaction_id'] ?? $payload['id'] ?? $orderNumber ?? 'unknown';

        $eventKey = "{$provider}:{$transactionId}:{$transactionStatus}";

        // Idempotency: jika event yang sama persis sudah tercatat dan diproses, kembalikan 200 OK langsung
        $log = PaymentWebhookLog::firstOrCreate(
            ['event_key' => $eventKey],
            [
                'provider' => $provider,
                'order_number' => $orderNumber,
                'status' => $transactionStatus,
                'payload' => $payload,
            ]
        );

        if ($log->isProcessed()) {
            return ApiResponse::success([
                'order_number' => $orderNumber,
                'status' => 'already_processed',
            ], 'Webhook already processed (idempotent)');
        }

        if (! $orderNumber) {
            $log->markProcessed();

            return ApiResponse::error(
                'Order number missing in webhook payload',
                null,
                HttpResponse::HTTP_BAD_REQUEST
            );
        }

        $order = Order::where('order_number', $orderNumber)->first();
        if (! $order) {
            $log->markProcessed();

            return ApiResponse::error(
                'Pesanan tidak ditemukan',
                null,
                HttpResponse::HTTP_NOT_FOUND
            );
        }

        // Status settlement / capture / success -> Lunas
        if (in_array($transactionStatus, ['settlement', 'capture', 'paid', 'COMPLETED', 'SUCCESS'], true)) {
            $paymentRef = (string) ($payload['transaction_id'] ?? $payload['payment_reference'] ?? $transactionId);
            $this->checkout->markAsPaid($order, $paymentRef);
        }
        // Status cancel / expire / deny -> Batalkan & kembalikan stok
        elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire', 'EXPIRED', 'FAILED'], true)) {
            $paymentStatus = match ($transactionStatus) {
                'expire', 'EXPIRED' => PaymentStatus::Expired,
                default => PaymentStatus::Failed,
            };
            $this->checkout->cancel($order, $paymentStatus);
        }

        $log->markProcessed();

        return ApiResponse::success([
            'order_number' => $order->order_number,
            'payment_status' => $order->payment_status->value,
            'fulfillment_status' => $order->fulfillment_status->value,
        ], 'Webhook processed successfully');
    }
}
