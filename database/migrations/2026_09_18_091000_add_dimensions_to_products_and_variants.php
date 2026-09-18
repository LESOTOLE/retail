<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('weight_gram')->default(250)->after('base_price');
            $table->decimal('length_cm', 6, 2)->nullable()->after('weight_gram');
            $table->decimal('width_cm', 6, 2)->nullable()->after('length_cm');
            $table->decimal('height_cm', 6, 2)->nullable()->after('width_cm');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->integer('weight_gram')->nullable()->after('min_stock_alert');
            $table->decimal('length_cm', 6, 2)->nullable()->after('weight_gram');
            $table->decimal('width_cm', 6, 2)->nullable()->after('length_cm');
            $table->decimal('height_cm', 6, 2)->nullable()->after('width_cm');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['weight_gram', 'length_cm', 'width_cm', 'height_cm']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['weight_gram', 'length_cm', 'width_cm', 'height_cm']);
        });
    }
};
