<?php

namespace App\Console\Commands;

use App\Services\CheckoutService;
use Illuminate\Console\Command;

class CancelExpiredOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:cancel-expired {--ttl=30 : Batas waktu toleransi pembayaran dalam menit (default: 30)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Batalkan pesanan unpaid yang kedaluwarsa (> 30 menit) dan kembalikan kuantitas stok varian (PRD 4.2).';

    /**
     * Execute the console command.
     */
    public function handle(CheckoutService $checkoutService): int
    {
        $ttl = (int) $this->option('ttl');
        $this->info("Memeriksa pesanan unpaid yang melebihi batas waktu {$ttl} menit...");

        $cancelledCount = $checkoutService->cancelExpiredOrders($ttl);

        if ($cancelledCount > 0) {
            $this->info("Sukses membatalkan {$cancelledCount} pesanan kedaluwarsa dan mengembalikan stok varian.");
        } else {
            $this->info('Tidak ada pesanan unpaid yang kedaluwarsa.');
        }

        return self::SUCCESS;
    }
}
