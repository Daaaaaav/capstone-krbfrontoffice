<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Each row represents one scan of the guestbook QR code.
     * Allows a group of visitors to each scan individually,
     * recording their own name/details on the public scan page.
     */
    public function up(): void
    {
        Schema::create('guestbook_scans', function (Blueprint $table) {
            $table->bigIncrements('scan_id');
            $table->unsignedBigInteger('guestbook_id');
            $table->foreign('guestbook_id')
                  ->references('guestbook_id')
                  ->on('guestbooks')
                  ->cascadeOnDelete();
            // Optional per-visitor details collected on the scan page
            $table->string('visitor_name')->nullable();
            $table->string('visitor_id_number')->nullable()->comment('KTP / employee ID');
            $table->string('scanned_by_ip', 45)->nullable();
            $table->timestamp('scanned_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guestbook_scans');
    }
};
