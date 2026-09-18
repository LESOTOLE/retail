<?php

namespace App\Services\AI;

use App\Enums\ChatSender;
use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\Vehicle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI Sales & Compatibility Assistant berbasis Gemini Function Calling (PRD 4.3 & Task 4).
 *
 * Alur:
 *  1. Riwayat percakapan + pesan baru dikirim ke Gemini bersama tool definitions.
 *  2. Bila Gemini meminta functionCall, tool dieksekusi ke database lokal.
 *  3. Hasil tool dikirim balik sebagai functionResponse (maks N iterasi).
 *  4. Jawaban teks final + daftar produk yang benar-benar ada dikembalikan.
 *
 * Bila GEMINI_API_KEY tidak dikonfigurasi, service memakai fallback pencarian
 * lokal sehingga endpoint tetap berfungsi tanpa layanan eksternal.
 */
class GeminiAIService
{
    public function __construct(
        protected ProductToolExecutor $tools
    ) {}

    /**
     * Menangani satu giliran percakapan.
     *
     * @return array{reply: string, recommended_products: array<int, array<string, mixed>>, tool_calls: array<int, array<string, mixed>>, driver: string}
     */
    public function handle(AiChatSession $session, string $message, ?Vehicle $vehicle = null): array
    {
        $history = $this->buildHistory($session);

        $session->pushMessage(ChatSender::User, $message, $vehicle ? ['vehicle_id' => $vehicle->id] : null);

        if (! $this->isConfigured()) {
            $result = $this->fallback($message, $vehicle);
        } else {
            try {
                $result = $this->runConversation($history, $message, $vehicle);
            } catch (ConnectionException|\RuntimeException $e) {
                Log::warning('MotoVault AI: panggilan Gemini gagal, memakai fallback lokal.', [
                    'error' => $e->getMessage(),
                ]);

                $result = $this->fallback($message, $vehicle);
            }
        }

        $session->pushMessage(ChatSender::Assistant, $result['reply'], [
            'driver' => $result['driver'],
            'tool_calls' => $result['tool_calls'],
            'recommended_products' => $result['recommended_products'],
        ]);

        return $result;
    }

    /**
     * Apakah kredensial Gemini tersedia.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    /**
     * System prompt dengan guardrails PRD 4.3.
     */
    public function systemPrompt(?Vehicle $vehicle = null): string
    {
        $context = $vehicle
            ? "Kendaraan pelanggan saat ini: {$vehicle->full_name} (vehicle_id={$vehicle->id}, tahun {$vehicle->year_range}). Gunakan vehicle_id ini pada setiap pemanggilan tool."
            : 'Pelanggan belum menyebutkan kendaraannya. Tanyakan merek, model, dan tahun motor sebelum merekomendasikan produk yang spesifik.';

        return implode(' ', [
            'Anda adalah asisten penjualan MotoVault, toko suku cadang dan aksesori otomotif di Indonesia.',
            'Jawab dalam Bahasa Indonesia yang ramah, ringkas, dan teknis seperlunya.',
            'ATURAN WAJIB: Dilarang keras mengarang SKU, nama produk, harga, atau stok.',
            'Semua informasi produk harus berasal dari hasil tool search_products, check_compatibility, atau get_product_detail.',
            'Jika data tidak ditemukan pada hasil tool, katakan terus terang bahwa stok/barang tersebut tidak tersedia,',
            'lalu tawarkan alternatif valid yang muncul pada hasil tool.',
            'Selalu sebutkan catatan pemasangan (compatibility note) bila tersedia.',
            $context,
        ]);
    }

    /**
     * Menyusun riwayat percakapan dalam format contents Gemini.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildHistory(AiChatSession $session, int $limit = 12): array
    {
        return $session->messages()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (AiChatMessage $m) => [
                'role' => $m->sender->toGeminiRole(),
                'parts' => [['text' => $m->message]],
            ])
            ->values()
            ->all();
    }

    /**
     * Loop function calling ke Gemini.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @return array{reply: string, recommended_products: array<int, array<string, mixed>>, tool_calls: array<int, array<string, mixed>>, driver: string}
     */
    protected function runConversation(array $history, string $message, ?Vehicle $vehicle): array
    {
        $contents = array_merge($history, [
            ['role' => 'user', 'parts' => [['text' => $message]]],
        ]);

        $toolCalls = [];
        $products = [];
        $maxIterations = max(1, (int) config('services.ai.max_tool_iterations', 4));

        for ($i = 0; $i < $maxIterations; $i++) {
            $response = $this->callGemini($contents, $vehicle);
            $parts = data_get($response, 'candidates.0.content.parts', []);
            $functionCalls = $this->extractFunctionCalls($parts);

            if ($functionCalls === []) {
                return [
                    'reply' => $this->extractText($parts) ?: 'Maaf, saya belum dapat memproses permintaan tersebut.',
                    'recommended_products' => $this->dedupeProducts($products),
                    'tool_calls' => $toolCalls,
                    'driver' => 'gemini',
                ];
            }

            $contents[] = ['role' => 'model', 'parts' => $parts];
            $responseParts = [];

            foreach ($functionCalls as $call) {
                $name = $call['name'];
                $args = $this->normalizeArguments($call['args'] ?? [], $vehicle);
                $result = $this->tools->execute($name, $args);

                $toolCalls[] = ['name' => $name, 'arguments' => $args, 'result' => $result];
                $products = array_merge($products, $this->collectProducts($result));

                $responseParts[] = [
                    'functionResponse' => [
                        'name' => $name,
                        'response' => ['result' => $result],
                    ],
                ];
            }

            $contents[] = ['role' => 'user', 'parts' => $responseParts];
        }

        // Batas iterasi tercapai: kembalikan rangkuman dari data tool yang sudah ada.
        return [
            'reply' => 'Berikut produk yang tersedia dan sesuai dengan kebutuhan Anda berdasarkan data katalog kami.',
            'recommended_products' => $this->dedupeProducts($products),
            'tool_calls' => $toolCalls,
            'driver' => 'gemini',
        ];
    }

    /**
     * Memanggil endpoint generateContent Gemini.
     *
     * @param  array<int, array<string, mixed>>  $contents
     * @return array<string, mixed>
     */
    protected function callGemini(array $contents, ?Vehicle $vehicle): array
    {
        $config = config('services.gemini');
        $url = rtrim($config['base_url'], '/')."/models/{$config['model']}:generateContent";

        $response = Http::timeout($config['timeout'])
            ->withHeaders(['x-goog-api-key' => $config['api_key']])
            ->acceptJson()
            ->post($url, [
                'system_instruction' => [
                    'parts' => [['text' => $this->systemPrompt($vehicle)]],
                ],
                'contents' => $contents,
                'tools' => [
                    ['function_declarations' => $this->tools->definitions()],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 1024,
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Gemini API error: '.$response->status().' '.$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Mengambil seluruh functionCall dari parts respons.
     *
     * @param  array<int, mixed>  $parts
     * @return array<int, array<string, mixed>>
     */
    protected function extractFunctionCalls(array $parts): array
    {
        $calls = [];

        foreach ($parts as $part) {
            $call = $part['functionCall'] ?? $part['function_call'] ?? null;

            if (is_array($call) && isset($call['name'])) {
                $calls[] = [
                    'name' => $call['name'],
                    'args' => $call['args'] ?? $call['arguments'] ?? [],
                ];
            }
        }

        return $calls;
    }

    /**
     * Menggabungkan seluruh bagian teks dari respons.
     *
     * @param  array<int, mixed>  $parts
     */
    protected function extractText(array $parts): string
    {
        $texts = [];

        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $texts[] = trim($part['text']);
            }
        }

        return trim(implode("\n", array_filter($texts)));
    }

    /**
     * Menyisipkan konteks vehicle_id bila AI lupa mengirimkannya.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function normalizeArguments(array $args, ?Vehicle $vehicle): array
    {
        if ($vehicle && ! isset($args['vehicle_id'])) {
            $args['vehicle_id'] = $vehicle->id;
        }

        return $args;
    }

    /**
     * Mengumpulkan produk dari hasil tool untuk payload recommended_products.
     *
     * @param  array<string, mixed>  $result
     * @return array<int, array<string, mixed>>
     */
    protected function collectProducts(array $result): array
    {
        $rows = [];

        foreach (['products', 'alternatives'] as $key) {
            if (isset($result[$key]) && is_array($result[$key])) {
                $rows = array_merge($rows, $result[$key]);
            }
        }

        // get_product_detail / check_compatibility mengembalikan satu produk.
        if (isset($result['sku'], $result['price'])) {
            $rows[] = [
                'sku' => $result['sku'],
                'name' => $result['name'] ?? trim(($result['product_name'] ?? '').' '.($result['variant_name'] ?? '')),
                'price' => $result['price'],
                'stock' => $result['stock'] ?? 0,
            ];
        }

        return $rows;
    }

    /**
     * Membuang duplikat SKU & menormalkan struktur payload (PRD 6.4).
     *
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    protected function dedupeProducts(array $products, int $limit = 10): array
    {
        $unique = [];

        foreach ($products as $product) {
            $sku = $product['sku'] ?? null;

            if (! $sku || isset($unique[$sku])) {
                continue;
            }

            $unique[$sku] = [
                'sku' => $sku,
                'name' => $product['name'] ?? '',
                'price' => (float) ($product['price'] ?? 0),
                'stock' => (int) ($product['stock'] ?? 0),
            ];

            if (isset($product['compatibility_note']) && $product['compatibility_note']) {
                $unique[$sku]['compatibility_note'] = $product['compatibility_note'];
            }
        }

        return array_slice(array_values($unique), 0, $limit);
    }

    /**
     * Fallback lokal tanpa LLM: menjalankan search_products langsung terhadap
     * database sehingga endpoint tetap mengembalikan data faktual.
     *
     * @return array{reply: string, recommended_products: array<int, array<string, mixed>>, tool_calls: array<int, array<string, mixed>>, driver: string}
     */
    protected function fallback(string $message, ?Vehicle $vehicle): array
    {
        $arguments = [
            'query' => $this->extractKeyword($message),
            'vehicle_id' => $vehicle?->id,
        ];

        $result = $this->tools->searchProducts(
            query: $arguments['query'],
            vehicleId: $vehicle?->id,
        );

        $products = $this->dedupeProducts($result['products']);

        return [
            'reply' => $this->composeFallbackReply($products, $vehicle),
            'recommended_products' => $products,
            'tool_calls' => [
                ['name' => 'search_products', 'arguments' => $arguments, 'result' => $result],
            ],
            'driver' => 'local-fallback',
        ];
    }

    /**
     * Mengambil kata kunci produk dari kalimat pengguna dengan membuang stopword.
     */
    protected function extractKeyword(string $message): ?string
    {
        $stopwords = [
            'rekomendasikan', 'rekomendasi', 'tolong', 'carikan', 'cari', 'saya', 'aku',
            'untuk', 'motor', 'yang', 'cocok', 'dan', 'atau', 'dong', 'apa', 'ada',
            'berapa', 'harga', 'mau', 'beli', 'punya', 'dengan', 'bisa', 'ya', 'kah',
        ];

        $words = preg_split('/[^\p{L}\p{N}\-]+/u', mb_strtolower($message), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keywords = array_values(array_diff($words, $stopwords));

        return $keywords === [] ? null : $keywords[0];
    }

    /**
     * Menyusun jawaban teks fallback berbasis data nyata.
     *
     * @param  array<int, array<string, mixed>>  $products
     */
    protected function composeFallbackReply(array $products, ?Vehicle $vehicle): string
    {
        $target = $vehicle ? " untuk {$vehicle->full_name}" : '';

        if ($products === []) {
            return "Maaf, saat ini kami tidak menemukan produk yang sesuai{$target} di katalog MotoVault. "
                .'Silakan sebutkan jenis komponen yang Anda cari (misal: oli, busi, aki, kampas rem) agar saya bisa mencarikan alternatif yang tersedia.';
        }

        $lines = [];

        foreach (array_slice($products, 0, 3) as $product) {
            $price = number_format($product['price'], 0, ',', '.');
            $lines[] = "- {$product['name']} (SKU {$product['sku']}) — Rp{$price}, stok {$product['stock']}";
        }

        return "Berikut rekomendasi produk yang tersedia{$target}:\n".implode("\n", $lines);
    }
}
