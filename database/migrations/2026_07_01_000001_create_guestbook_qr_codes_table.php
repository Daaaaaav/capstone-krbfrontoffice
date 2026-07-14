<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Individual QR codes for each visitor in a guestbook group.
     * One row per visitor (e.g. visitor_count=50 → 50 rows).
     * Each has a unique token for checkout scanning.
     */
    public function up(): void
    {
        Schema::create('guestbook_qr_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('guestbook_id');
            $table->foreign('guestbook_id')
                  ->references('guestbook_id')
                  ->on('guestbooks')
                  ->cascadeOnDelete();
            $table->string('qr_token', 64)->unique();
            $table->unsignedSmallInteger('visitor_number')->comment('1-based index within group');
            $table->boolean('is_scanned')->default(false);
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guestbook_qr_codes');
    }
};
