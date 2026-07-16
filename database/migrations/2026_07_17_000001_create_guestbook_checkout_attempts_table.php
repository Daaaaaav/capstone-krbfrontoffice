<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Every scan attempt on the checkout page — both successful and failed.
     * Successful rows reference the visitor_number that was checked out.
     * Failed rows record the error type so the receptionist can review them.
     */
    public function up(): void
    {
        Schema::create('guestbook_checkout_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('guestbook_id');
            $table->foreign('guestbook_id')
                  ->references('guestbook_id')
                  ->on('guestbooks')
                  ->cascadeOnDelete();

            // Whether the scan succeeded (visitor was checked out)
            $table->boolean('success')->default(false);

            // Human-readable result shown in the UI log
            $table->string('message', 300);

            // Visitor number from guestbook_qr_codes — null for failed/invalid scans
            $table->unsignedSmallInteger('visitor_number')->nullable();

            // Error type for failed attempts: already_scanned | invalid | wrong_entry | completed | error
            $table->string('error_type', 50)->nullable();

            $table->timestamp('attempted_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guestbook_checkout_attempts');
    }
};
