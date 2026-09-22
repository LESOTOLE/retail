<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Report\SalesReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller untuk laporan dan ekspor rekapitulasi penjualan (PRD 4.5 & 6.8).
 */
class SalesReportController extends Controller
{
    public function __construct(
        protected SalesReportService $reportService
    ) {}

    /**
     * GET /api/v1/admin/reports/sales
     * Mengembalikan ringkasan omset & daftar transaksi terfilter.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'payment_status' => ['nullable', 'string'],
            'payment_method' => ['nullable', 'string'],
        ]);

        $summary = $this->reportService->getSummary($filters);
        $orders = $this->reportService->queryOrders($filters)->paginate(25);

        return ApiResponse::success([
            'summary' => $summary,
            'orders' => $orders,
        ], 'Laporan penjualan berhasil dimuat');
    }

    /**
     * GET /api/v1/admin/reports/sales/export
     * Menghasilkan dan men-download file CSV laporan penjualan.
     */
    public function exportCsv(Request $request): Response
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'payment_status' => ['nullable', 'string'],
            'payment_method' => ['nullable', 'string'],
        ]);

        $csv = $this->reportService->generateCsv($filters);
        $filename = 'laporan-penjualan-motovault-' . now()->format('Ymd-His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
