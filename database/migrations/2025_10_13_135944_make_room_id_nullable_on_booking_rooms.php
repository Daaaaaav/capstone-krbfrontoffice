<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable()->change();
        });
    }

    public function down(): void {
        \Illuminate\Support\Facades\DB::statement(
            'UPDATE booking_rooms SET room_id = 0 WHERE room_id IS NULL'
        );
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable(false)->default(0)->change();
        });
    }
};
