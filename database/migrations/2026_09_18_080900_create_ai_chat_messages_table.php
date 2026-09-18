<?php

use App\Enums\ChatSender;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD 5 - ai_chat_messages: riwayat pesan & audit log function calling AI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('ai_chat_sessions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->enum('sender', ChatSender::values());
            $table->text('message');
            $table->json('raw_payload')->nullable()
                ->comment('Parameter & hasil function calling untuk audit log AI');
            $table->timestamps();

            $table->index(['session_id', 'created_at'], 'ai_messages_session_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
