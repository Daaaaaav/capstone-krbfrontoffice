<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a flag to distinguish Manager-scheduled guestbook entries
     * (created via the Manager → Guestbook Form) from walk-in entries
     * recorded by the Receptionist at the front desk.
     *
     * Only Manager-scheduled entries should display the "Scheduled Guest"
     * banner on the Guestbook Status page.
     */
    public function up(): void
    {
        Schema::table('guestbooks', function (Blueprint $table) {
            if (! Schema::hasColumn('guestbooks', 'scheduled_by_manager')) {
                $table->boolean('scheduled_by_manager')
                      ->default(false)
                      ->after('storage_place')
                      ->comment('true when this entry was pre-scheduled by a Manager via GuestbookForm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guestbooks', function (Blueprint $table) {
            if (Schema::hasColumn('guestbooks', 'scheduled_by_manager')) {
                $table->dropColumn('scheduled_by_manager');
            }
        });
    }
};
