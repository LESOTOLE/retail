<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD 5 - product_vehicles: pivot many-to-many produk <-> kendaraan (PRD 4.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('notes', 255)->nullable()
                ->comment("Contoh: 'Plug and play', 'Butuh bracket tambahan'");
            $table->timestamps();

            $table->unique(['product_id', 'vehicle_id'], 'product_vehicles_unique');
            $table->index('vehicle_id', 'product_vehicles_vehicle_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_vehicles');
    }
};
