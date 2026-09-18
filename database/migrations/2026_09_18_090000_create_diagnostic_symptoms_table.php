<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_symptoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('symptom_title', 200);
            $table->text('symptom_description')->nullable();
            $table->json('symptom_keywords')->nullable();
            $table->text('suspected_root_cause');
            $table->string('severity', 30)->default('medium');
            $table->json('recommended_part_types');
            $table->json('embedding')->nullable();
            $table->timestamps();

            $table->index('severity', 'diagnostic_severity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_symptoms');
    }
};
