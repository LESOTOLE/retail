<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Health Check & Observability Endpoint (PRD 9.2).
 */
class HealthController extends Controller
{
    /**
     * GET /api/v1/health
     */
    public function check(): JsonResponse
    {
        $dbStatus = 'ok';
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $dbStatus = 'error: '.$e->getMessage();
        }

        $cacheStatus = 'ok';
        try {
            Cache::put('health_check', 1, 10);
            $cacheStatus = Cache::get('health_check') === 1 ? 'ok' : 'degraded';
        } catch (Throwable $e) {
            $cacheStatus = 'error: '.$e->getMessage();
        }

        $geminiConfigured = filled(config('services.gemini.api_key'));

        $allOk = $dbStatus === 'ok' && $cacheStatus === 'ok';

        return ApiResponse::success([
            'status' => $allOk ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $dbStatus,
                'cache' => $cacheStatus,
                'ai_gemini_configured' => $geminiConfigured,
            ],
        ], 'System health check completed');
    }
}
