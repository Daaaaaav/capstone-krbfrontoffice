<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds handover_photo and return_photo fields to priority_vehicle_bookings table
     * to support the proof photo workflow similar to ordinary vehicle_bookings.
     */
    public function up(): void
    {
        Schema::table('priority_vehicle_bookings', function (Blueprint $table) {
            $table->string('handover_photo')->nullable()->after('rejection_reason');
            $table->string('return_photo')->nullable()->after('handover_photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('priority_vehicle_bookings', function (Blueprint $table) {
            $table->dropColumn(['handover_photo', 'return_photo']);
        });
    }
};
