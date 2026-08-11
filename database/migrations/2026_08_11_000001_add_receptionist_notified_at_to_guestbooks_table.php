<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guestbooks', function (Blueprint $table) {
            if (! Schema::hasColumn('guestbooks', 'receptionist_notified_at')) {
                $table->timestamp('receptionist_notified_at')
                      ->nullable()
                      ->after('scheduled_by_manager')
                      ->comment('Set when the receptionist notification is dispatched for a scheduled visitor; prevents duplicate notifications on repeated scheduler runs.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guestbooks', function (Blueprint $table) {
            if (Schema::hasColumn('guestbooks', 'receptionist_notified_at')) {
                $table->dropColumn('receptionist_notified_at');
            }
        });
    }
};
