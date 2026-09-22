<?php

namespace App\Services\Report;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Service untuk kalkulasi rekapitulasi penjualan omnichannel & ekspor CSV/Excel (PRD 4.5 & 6.8).
 */
class SalesReportService
{
    /**
     * Membangun base query berdasarkan filter tanggal, gudang, status, dan metode bayar.
     *
     * @param array<string, mixed> $filters
     */
    public function queryOrders(array $filters = []): Builder
    {
        $query = Order::with(['items.variant.product', 'user', 'warehouse']);

        if (! empty($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->startOfDay();
            $query->where('created_at', '>=', $startDate);
        }

        if (! empty($filters['end_date'])) {
            $endDate = Carbon::parse($filters['end_date'])->endOfDay();
            $query->where('created_at', '<=', $endDate);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        return $query->latest('id');
    }

    /**
     * Mengkalkulasi ringkasan omset penjualan dan KPI toko.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters = []): array
    {
        $orders = $this->queryOrders($filters)->get();

        $totalOmset = (float) $orders->sum('total_amount');
        $totalTransaksi = $orders->count();
        $totalItemsSold = 0;

        foreach ($orders as $order) {
            $totalItemsSold += (int) $order->items->sum('quantity');
        }

        // Breakdown per metode pembayaran
        $byPaymentMethod = $orders->groupBy(fn (Order $o) => $o->payment_method ?: 'LAINNYA')
            ->map(fn (Collection $group, $method) => [
                'method' => $method,
                'count' => $group->count(),
                'total_amount' => (float) $group->sum('total_amount'),
            ])->values();

        // Breakdown per cabang gudang
        $byWarehouse = $orders->groupBy(fn (Order $o) => $o->warehouse?->name ?: 'Gudang Pusat / Default')
            ->map(fn (Collection $group, $whName) => [
                'warehouse_name' => $whName,
                'count' => $group->count(),
                'total_amount' => (float) $group->sum('total_amount'),
            ])->values();

        return [
            'total_omset' => $totalOmset,
            'total_transaksi' => $totalTransaksi,
            'total_unit_terjual' => $totalItemsSold,
            'omset_per_metode' => $byPaymentMethod,
            'omset_per_gudang' => $byWarehouse,
            'periode' => [
                'start_date' => $filters['start_date'] ?? null,
                'end_date' => $filters['end_date'] ?? null,
            ],
        ];
    }

    /**
     * Menghasilkan string file CSV terstandarisasi RFC-4180 dengan UTF-8 BOM untuk Excel.
     *
     * @param array<string, mixed> $filters
     */
    public function generateCsv(array $filters = []): string
    {
        $orders = $this->queryOrders($filters)->get();

        $handle = fopen('php://temp', 'r+');
        if (! $handle) {
            return '';
        }

        // Tambahkan UTF-8 BOM agar terbaca sempurna di Microsoft Excel
        fputs($handle, "\xEF\xBB\xBF");

        // Header CSV
        fputcsv($handle, [
            'No',
            'Tanggal Transaksi',
            'No. Pesanan',
            'Tipe Transaksi',
            'Cabang Gudang',
            'Nama Pelanggan',
            'Metode Pembayaran',
            'Status Pembayaran',
            'Status Fulfillment',
            'Jumlah Item',
            'Total Nilai (Rp)',
        ]);

        $no = 1;
        foreach ($orders as $order) {
            $isPos = str_starts_with((string) $order->shipping_address, 'POS');
            $tipe = $isPos ? 'POS Kasir Toko' : 'Storefront Online';
            $warehouseName = $order->warehouse?->name ?? 'Gudang Pusat';
            $customerName = $order->user?->name ?? 'Pelanggan Toko';
            $itemCount = $order->items->sum('quantity');

            fputcsv($handle, [
                $no++,
                $order->created_at?->format('Y-m-d H:i:s') ?? '-',
                $order->order_number,
                $tipe,
                $warehouseName,
                $customerName,
                strtoupper((string) $order->payment_method),
                strtoupper($order->payment_status?->value ?? (string) $order->payment_status),
                strtoupper($order->fulfillment_status?->value ?? (string) $order->fulfillment_status),
                $itemCount,
                (float) $order->total_amount,
            ]);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csvContent;
    }
}
