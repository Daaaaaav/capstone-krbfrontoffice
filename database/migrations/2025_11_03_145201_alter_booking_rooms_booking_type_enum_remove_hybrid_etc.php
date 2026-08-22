<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('booking_rooms')
            ->where('booking_type', 'hybrid')
            ->update(['booking_type' => 'online_meeting']);

        DB::table('booking_rooms')
            ->where('booking_type', 'etc')
            ->update(['booking_type' => 'meeting']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE `booking_rooms`
                MODIFY `booking_type`
                ENUM('meeting','online_meeting')
                CHARACTER SET utf8mb4
                COLLATE utf8mb4_unicode_ci
                NOT NULL
                DEFAULT 'meeting'
            ");
        }
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `booking_rooms`
            MODIFY `booking_type`
            ENUM('meeting','online_meeting','hybrid','etc')
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci
            NOT NULL
            DEFAULT 'meeting'
        ");
    }
};
