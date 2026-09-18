<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('courier_code', 30);
            $table->string('courier_service', 50);
            $table->string('waybill_number', 100)->nullable();
            $table->string('tracking_status', 40)->default('ready_to_ship');
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('insurance_cost', 12, 2)->default(0);
            $table->string('origin_postal_code', 10)->default('12190');
            $table->string('destination_postal_code', 10);
            $table->text('destination_address')->nullable();
            $table->json('raw_tracking_history')->nullable();
            $table->timestamps();

            $table->index(['courier_code', 'waybill_number'], 'shipping_waybill_index');
            $table->index('tracking_status', 'shipping_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_orders');
    }
};
