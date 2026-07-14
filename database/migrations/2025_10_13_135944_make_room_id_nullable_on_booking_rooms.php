<?php

// database/migrations/xxxx_xx_xx_make_room_id_nullable_on_booking_rooms.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('booking_rooms', function (Blueprint $table) {
            // if a foreign key exists, drop it first, then make column nullable, then recreate if desired
            // $table->dropForeign(['room_id']);
            $table->unsignedBigInteger('room_id')->nullable()->change();
        });
    }

    public function down(): void {
        // Replace NULLs with 0 before reverting to NOT NULL —
        // online meeting bookings have no room_id; use 0 as a sentinel.
        \Illuminate\Support\Facades\DB::statement(
            'UPDATE booking_rooms SET room_id = 0 WHERE room_id IS NULL'
        );
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable(false)->default(0)->change();
        });
    }
};
