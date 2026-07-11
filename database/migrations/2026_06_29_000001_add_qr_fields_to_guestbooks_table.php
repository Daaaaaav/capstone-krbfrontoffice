<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('guestbooks', function (Blueprint $table) {
            if (!Schema::hasColumn('guestbooks', 'email')) {
                $table->string('email')->nullable()->after('name');
            }
            if (!Schema::hasColumn('guestbooks', 'qr_token')) {
                $table->string('qr_token', 64)->nullable()->unique()->after('email');
            }
            if (!Schema::hasColumn('guestbooks', 'qr_status')) {
                // 'pending'   = QR sent, not yet scanned
                // 'ongoing'   = QR scanned at least once (visitor confirmed onsite)
                // 'completed' = jam_out is set
                $table->enum('qr_status', ['pending', 'ongoing', 'completed'])
                      ->default('pending')
                      ->after('qr_token');
            }
            if (!Schema::hasColumn('guestbooks', 'visitor_count')) {
                $table->unsignedSmallInteger('visitor_count')
                      ->default(0)
                      ->comment('Number of times the QR code has been scanned (i.e. group size)')
                      ->after('qr_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guestbooks', function (Blueprint $table) {
            $table->dropColumn(['email', 'qr_token', 'qr_status', 'visitor_count']);
        });
    }
};
