<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE booking_rooms 
            MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed') 
            NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE booking_rooms SET status = 'approved' WHERE status = 'completed'");

        DB::statement("ALTER TABLE booking_rooms 
            MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') 
            NOT NULL DEFAULT 'pending'");
    }
};
