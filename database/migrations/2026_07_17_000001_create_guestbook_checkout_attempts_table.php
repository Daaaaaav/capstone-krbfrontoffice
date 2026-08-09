<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('guestbook_checkout_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('guestbook_id');
            $table->foreign('guestbook_id')
                  ->references('guestbook_id')
                  ->on('guestbooks')
                  ->cascadeOnDelete();
            $table->boolean('success')->default(false);
            $table->string('message', 300);
            $table->unsignedSmallInteger('visitor_number')->nullable();
            $table->string('error_type', 50)->nullable();
            $table->timestamp('attempted_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guestbook_checkout_attempts');
    }
};
