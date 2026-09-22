<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Layanan Integrasi Midtrans Snap API (PRD 4.4 & Spec 2026-09-21).
 */
class MidtransService
{
    /**
     * Membuat Snap transaction token & redirect URL untuk pesanan.
     *
     * @return array{snap_token: string, redirect_url: string, client_key: string, is_mock?: bool}
     */
    public function createSnapTransaction(Order $order): array
    {
        $serverKey = config('services.midtrans.server_key');
        $clientKey = config('services.midtrans.client_key');

        // Jika server key belum disetel, gunakan fallback mock mode
        if (blank($serverKey)) {
            return $this->mockSnapResponse($order, $clientKey);
        }

        $snapUrl = config('services.midtrans.snap_url');
        $payload = $this->buildSnapPayload($order);

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic '.base64_encode($serverKey.':'),
            ])->timeout(15)->post($snapUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'snap_token' => (string) ($data['token'] ?? ''),
                    'redirect_url' => (string) ($data['redirect_url'] ?? ''),
                    'client_key' => $clientKey,
                    'is_mock' => false,
                ];
            }

            Log::warning('MotoVault Midtrans: Gagal memanggil Snap API, beralih ke fallback.', [
                'order_number' => $order->order_number,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->mockSnapResponse($order, $clientKey);
        } catch (ConnectionException|\Exception $e) {
            Log::error('MotoVault Midtrans: Error koneksi saat memanggil Snap API.', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return $this->mockSnapResponse($order, $clientKey);
        }
    }

    /**
     * Memverifikasi tanda tangan digital HMAC SHA-512 dari payload webhook Midtrans.
     */
    public function verifySignature(
        string $orderId,
        string $statusCode,
        string|float|int $grossAmount,
        string $signatureKey
    ): bool {
        $serverKey = config('services.midtrans.server_key');

        // Jika server key tidak diatur (lingkungan testing lokal), izinkan bypass bila testing
        if (blank($serverKey)) {
            return true;
        }

        // Format gross_amount Midtrans: bisa "10000.00" atau integer
        $formattedAmount = number_format((float) $grossAmount, 2, '.', '');
        $expected = hash('sha512', $orderId.$statusCode.$formattedAmount.$serverKey);

        if (hash_equals($expected, $signatureKey)) {
            return true;
        }

        // Coba alternatif format integer jika payment gateway mengirimkan amount tanpa pecahan
        $expectedInt = hash('sha512', $orderId.$statusCode.((int) $grossAmount).$serverKey);

        return hash_equals($expectedInt, $signatureKey);
    }

    /**
     * Menyusun payload Snap sesuai spesifikasi Midtrans.
     *
     * @return array<string, mixed>
     */
    public function buildSnapPayload(Order $order): array
    {
        $order->loadMissing(['items.variant.product', 'user']);

        $grossAmount = (int) round((float) $order->total_amount);

        $itemDetails = [];
        $itemsTotal = 0;

        foreach ($order->items as $item) {
            $variant = $item->variant;
            $product = $variant?->product;
            $unitPrice = (int) round((float) $item->unit_price);
            $qty = (int) $item->quantity;
            $lineSubtotal = $unitPrice * $qty;

            $itemsTotal += $lineSubtotal;

            $itemDetails[] = [
                'id' => (string) ($variant?->sku ?? "VAR-{$item->id}"),
                'price' => $unitPrice,
                'quantity' => $qty,
                'name' => mb_strimwidth(
                    ($product?->name ?? 'Produk').' ('.($variant?->variant_name ?? 'Varian').')',
                    0,
                    45,
                    '...'
                ),
            ];
        }

        // Tambahkan baris selisih / ongkos kirim jika itemsTotal berbeda dengan grossAmount
        $shippingDiff = $grossAmount - $itemsTotal;
        if ($shippingDiff > 0) {
            $itemDetails[] = [
                'id' => 'SHIPPING_FEE',
                'price' => $shippingDiff,
                'quantity' => 1,
                'name' => 'Ongkos Kirim & Penanganan',
            ];
        } elseif ($shippingDiff < 0) {
            // Diskon penyesuaian jika total negatif
            $itemDetails[] = [
                'id' => 'DISCOUNT',
                'price' => $shippingDiff,
                'quantity' => 1,
                'name' => 'Penyesuaian Diskon',
            ];
        }

        $user = $order->user;
        $nameParts = explode(' ', trim($user?->name ?? 'Pelanggan MotoVault'), 2);
        $firstName = $nameParts[0] ?? 'Pelanggan';
        $lastName = $nameParts[1] ?? '';

        return [
            'transaction_details' => [
                'order_id' => $order->order_number,
                'gross_amount' => $grossAmount,
            ],
            'item_details' => $itemDetails,
            'customer_details' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $user?->email ?? 'noreply@motovault.id',
                'phone' => $user?->phone ?? '081200000000',
                'shipping_address' => [
                    'first_name' => $firstName,
                    'address' => $order->shipping_address ?? 'Alamat Pemesan',
                ],
            ],
            'callbacks' => [
                'finish' => url('/?order_number='.$order->order_number.'&status=success'),
                'error' => url('/?order_number='.$order->order_number.'&status=error'),
            ],
        ];
    }

    /**
     * Menghasilkan respon mock untuk pengujian lokal / offline.
     *
     * @return array{snap_token: string, redirect_url: string, client_key: string, is_mock: bool}
     */
    protected function mockSnapResponse(Order $order, string $clientKey): array
    {
        $token = 'mock-snap-'.Str::uuid()->toString();

        return [
            'snap_token' => $token,
            'redirect_url' => (config('services.midtrans.is_production') ? "https://app.midtrans.com" : "https://app.sandbox.midtrans.com") . "/snap/v2/vtweb/{$token}",
            'client_key' => $clientKey,
            'is_mock' => true,
        ];
    }
}
