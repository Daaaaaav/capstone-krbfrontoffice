<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Sessions ─────────────────────────────────────────────
        // One row per conversation (a new session starts when the
        // user clicks "Clear" or opens the chat for the first time).
        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('role', 20);           // 'manager' | 'receptionist'
            $table->string('title', 255)->nullable(); // auto-set from first user message
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
        });

        // ── Messages ─────────────────────────────────────────────
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                  ->constrained('ai_chat_sessions')
                  ->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->text('text');
            // booking_prefill stored as JSON (nullable — only receptionist assistant msgs)
            $table->json('booking_prefill')->nullable();
            $table->timestamp('sent_at')->useCurrent();

            $table->index(['session_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_sessions');
    }
};
