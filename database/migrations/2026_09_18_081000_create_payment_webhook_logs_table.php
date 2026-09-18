<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel pendukung (di luar ERD PRD) untuk menjamin webhook payment
 * bersifat IDEMPOTENT sesuai PRD bagian 4.4.
 *
 * event_key dibentuk dari provider + id transaksi + status sehingga callback
 * berulang dengan payload sama tidak diproses dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->default('midtrans');
            $table->string('event_key', 190)->unique();
            $table->string('order_number', 32)->nullable();
            $table->string('status', 60)->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('order_number', 'webhook_logs_order_number_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
    }
};
