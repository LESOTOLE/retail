<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-Release Stok Terkunci untuk Pesanan Unpaid (TTL 30 Menit - PRD 4.2)
\Illuminate\Support\Facades\Schedule::command('orders:cancel-expired')->everyMinute();

