<?php

namespace App\Providers;

use App\Support\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Konfigurasi pembatasan laju kueri (PRD 8.3 & Spec 2026-09-21).
     */
    protected function configureRateLimiting(): void
    {
        // 1. AI Chat: Maksimal 15 request per menit
        RateLimiter::for('ai-chat', function (Request $request) {
            return Limit::perMinute(15)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return ApiResponse::error(
                        'Batas permintaan konsultasi AI tercapai. Silakan coba lagi dalam 1 menit.',
                        null,
                        HttpResponse::HTTP_TOO_MANY_REQUESTS,
                        'TOO_MANY_REQUESTS'
                    );
                });
        });

        // 2. Auth: Maksimal 10 percobaan per menit
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return ApiResponse::error(
                        'Terlalu banyak percobaan otentikasi. Silakan tunggu beberapa saat.',
                        null,
                        HttpResponse::HTTP_TOO_MANY_REQUESTS,
                        'TOO_MANY_REQUESTS'
                    );
                });
        });

        // 3. Catalog: Maksimal 120 request per menit
        RateLimiter::for('catalog', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return ApiResponse::error(
                        'Terlalu banyak permintaan katalog. Silakan coba lagi beberapa saat.',
                        null,
                        HttpResponse::HTTP_TOO_MANY_REQUESTS,
                        'TOO_MANY_REQUESTS'
                    );
                });
        });

        // 4. Checkout: Maksimal 10 request per menit
        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return ApiResponse::error(
                        'Terlalu banyak percobaan checkout. Silakan tunggu 1 menit.',
                        null,
                        HttpResponse::HTTP_TOO_MANY_REQUESTS,
                        'TOO_MANY_REQUESTS'
                    );
                });
        });

        // 5. Payment Webhook: Maksimal 60 request per menit
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return ApiResponse::error(
                        'Webhook rate limit reached.',
                        null,
                        HttpResponse::HTTP_TOO_MANY_REQUESTS,
                        'TOO_MANY_REQUESTS'
                    );
                });
        });
    }
}
