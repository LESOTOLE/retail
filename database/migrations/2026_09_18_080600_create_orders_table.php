<?php

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD 5 - orders + PRD 4.4 Order & Checkout Workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique()->comment('Format: ORD-YYYYMMDD-XXX');
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('payment_status', PaymentStatus::values())
                ->default(PaymentStatus::Unpaid->value);
            $table->enum('fulfillment_status', FulfillmentStatus::values())
                ->default(FulfillmentStatus::Pending->value);
            $table->string('payment_method', 50)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->text('shipping_address')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'payment_status'], 'orders_user_payment_index');
            $table->index('fulfillment_status', 'orders_fulfillment_index');
            $table->index('payment_reference', 'orders_payment_reference_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
