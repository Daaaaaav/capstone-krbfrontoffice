<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE vehicle_bookings
                MODIFY COLUMN status ENUM(
                    'pending',
                    'approved',
                    'on_progress',
                    'returned',
                    'completed',
                    'rejected',
                    'cancelled',
                    'late_return'
                ) NOT NULL DEFAULT 'pending'
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE vehicle_bookings SET status = 'approved' WHERE status = 'late_return'
            ");

            DB::statement("
                ALTER TABLE vehicle_bookings
                MODIFY COLUMN status ENUM(
                    'pending',
                    'approved',
                    'on_progress',
                    'returned',
                    'completed',
                    'rejected',
                    'cancelled'
                ) NOT NULL DEFAULT 'pending'
            ");
        }
    }
};
