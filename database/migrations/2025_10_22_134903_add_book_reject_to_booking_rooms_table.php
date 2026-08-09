<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('booking_rooms', function (Blueprint $table) {
            // Add the new column after 'status' for clarity
            $table->text('book_reject')->nullable()->after('status')->comment('Reason or notes for rejection');
        });
    }

  
    public function down(): void
    {
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->dropColumn('book_reject');
        });
    }
};
