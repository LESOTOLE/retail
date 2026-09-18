<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD 5 - product_variants + PRD 4.2 Safety Stock Alert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('variant_name', 120);
            $table->decimal('additional_price', 12, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->integer('min_stock_alert')->default(5);
            $table->timestamps();

            $table->index(['product_id', 'stock'], 'variants_product_stock_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
