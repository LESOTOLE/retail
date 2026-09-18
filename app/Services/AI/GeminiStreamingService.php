<?php

namespace App\Services\AI;

use App\Enums\ChatSender;
use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service untuk menangani respons AI secara real-time melalui Server-Sent Events (SSE).
 */
class GeminiStreamingService
{
    public function __construct(
        protected ProductToolExecutor $tools,
        protected GeminiAIService $aiService
    ) {}

    /**
     * Membuka koneksi SSE dan mengalirkan event stream.
     */
    public function stream(AiChatSession $session, string $message, ?Vehicle $vehicle = null): StreamedResponse
    {
        return response()->stream(function () use ($session, $message, $vehicle) {
            // 1. Emit Session Initial Event
            $this->sendEvent('session', [
                'session_token' => $session->session_token,
                'vehicle_id' => $vehicle?->id,
                'vehicle_name' => $vehicle?->full_name,
            ]);

            $session->pushMessage(ChatSender::User, $message, $vehicle ? ['vehicle_id' => $vehicle->id] : null);

            // 2. Stream generation
            if (! $this->aiService->isConfigured()) {
                $this->streamFallback($session, $message, $vehicle);
            } else {
                try {
                    $this->streamGemini($session, $message, $vehicle);
                } catch (\Throwable $e) {
                    Log::warning('MotoVault AI Streaming: Gemini stream gagal, beralih ke fallback', [
                        'error' => $e->getMessage(),
                    ]);
                    $this->streamFallback($session, $message, $vehicle);
                }
            }

            // 3. Emit Done Event
            $this->sendEvent('done', [
                'status' => 'complete',
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Alur streaming fallback berbasis database lokal.
     */
    protected function streamFallback(AiChatSession $session, string $message, ?Vehicle $vehicle): void
    {
        $keyword = $this->extractKeyword($message);

        $this->sendEvent('tool_start', [
            'name' => 'search_products',
            'arguments' => ['query' => $keyword, 'vehicle_id' => $vehicle?->id],
        ]);

        $result = $this->tools->searchProducts(query: $keyword, vehicleId: $vehicle?->id);
        $products = $this->dedupeProducts($result['products'] ?? []);

        $this->sendEvent('tool_result', [
            'count' => count($products),
            'products' => $products,
        ]);

        $reply = $this->composeReply($products, $vehicle);

        // Streaming token
        $chunks = preg_split('/(\s+)/u', $reply, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$reply];
        foreach ($chunks as $chunk) {
            $this->sendEvent('token', ['text' => $chunk]);
        }

        if (! empty($products)) {
            $this->sendEvent('product_recommendations', $products);
        }

        $session->pushMessage(ChatSender::Assistant, $reply, [
            'driver' => 'local-streaming-fallback',
            'recommended_products' => $products,
        ]);
    }

    /**
     * Alur streaming memanggil API Gemini.
     */
    protected function streamGemini(AiChatSession $session, string $message, ?Vehicle $vehicle): void
    {
        // Eksekusi percakapan via aiService lalu stream hasil finalnya
        $result = $this->aiService->handle($session, $message, $vehicle);

        if (! empty($result['tool_calls'])) {
            foreach ($result['tool_calls'] as $call) {
                $this->sendEvent('tool_result', $call);
            }
        }

        $reply = $result['reply'] ?? '';
        $chunks = preg_split('/(\s+)/u', $reply, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$reply];

        foreach ($chunks as $chunk) {
            $this->sendEvent('token', ['text' => $chunk]);
        }

        if (! empty($result['recommended_products'])) {
            $this->sendEvent('product_recommendations', $result['recommended_products']);
        }
    }

    /**
     * Mengirim paket format Server-Sent Events (SSE).
     *
     * @param  mixed  $data
     */
    protected function sendEvent(string $event, $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.(is_string($data) ? $data : json_encode($data))."\n\n";
        if (function_exists('flush')) {
            flush();
        }
    }

    protected function extractKeyword(string $message): ?string
    {
        $stopwords = ['rekomendasikan', 'rekomendasi', 'tolong', 'cari', 'saya', 'untuk', 'motor', 'yang', 'cocok'];
        $words = preg_split('/[^\p{L}\p{N}\-]+/u', mb_strtolower($message), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keywords = array_values(array_diff($words, $stopwords));

        return $keywords === [] ? null : $keywords[0];
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    protected function dedupeProducts(array $products): array
    {
        $unique = [];
        foreach ($products as $p) {
            $sku = $p['sku'] ?? null;
            if ($sku && ! isset($unique[$sku])) {
                $unique[$sku] = [
                    'sku' => $sku,
                    'name' => $p['name'] ?? '',
                    'price' => (float) ($p['price'] ?? 0),
                    'stock' => (int) ($p['stock'] ?? 0),
                    'compatibility_note' => $p['compatibility_note'] ?? null,
                ];
            }
        }

        return array_values($unique);
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    protected function composeReply(array $products, ?Vehicle $vehicle): string
    {
        $target = $vehicle ? " untuk {$vehicle->full_name}" : '';

        if ($products === []) {
            return "Maaf, saat ini kami tidak menemukan produk yang sesuai{$target} di katalog MotoVault.";
        }

        $lines = [];
        foreach (array_slice($products, 0, 3) as $p) {
            $price = number_format($p['price'], 0, ',', '.');
            $lines[] = "- {$p['name']} (SKU {$p['sku']}) — Rp{$price}, stok {$p['stock']}";
        }

        return "Berikut rekomendasi produk yang tersedia{$target}:\n".implode("\n", $lines);
    }
}
