<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('priority_vehicle_bookings', function (Blueprint $table) {
            $table->string('handover_photo')->nullable();
            $table->string('return_photo')->nullable();
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
