<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->text('address');
            $table->string('city', 100);
            $table->string('postal_code', 10);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_central')->default(false);
            $table->timestamps();

            $table->index('is_active');
            $table->index('city');
        });

        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->integer('stock')->default(0);
            $table->integer('min_stock_alert')->default(3);
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_variant_id'], 'warehouse_variant_unique');
            $table->index(['warehouse_id', 'stock'], 'warehouse_stock_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
        Schema::dropIfExists('warehouses');
    }
};
