<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD 5 - vehicles: master kendaraan untuk Vehicle Compatibility Engine (PRD 4.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('brand', 60);
            $table->string('model', 120);
            $table->smallInteger('year_start')->unsigned();
            $table->smallInteger('year_end')->unsigned()->nullable()
                ->comment('NULL berarti masih diproduksi hingga sekarang');
            $table->timestamps();

            $table->unique(['brand', 'model', 'year_start'], 'vehicles_brand_model_year_unique');
            $table->index('brand', 'vehicles_brand_index');
            $table->index(['brand', 'model'], 'vehicles_brand_model_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
