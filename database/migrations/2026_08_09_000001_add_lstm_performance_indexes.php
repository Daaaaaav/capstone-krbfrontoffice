<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add indexes to optimize LSTM time series queries for live_db mode.
     * These indexes speed up the aggregation queries used by OccupancyForecasting.
     */
    public function up(): void
    {
        Schema::table('booking_rooms', function (Blueprint $table) {
            // Optimize: selectRaw('DATE(created_at) as date, COUNT(*) as count')
            //           ->where('company_id', $companyId)
            //           ->groupByRaw('DATE(created_at)')
            //           ->orderByRaw('DATE(created_at)')
            
            // Add composite index on company_id and created_at for efficient filtering and date extraction
            $table->index(['company_id', 'created_at'], 'idx_booking_rooms_company_created');
        });

        Schema::table('vehicle_bookings', function (Blueprint $table) {
            // Optimize: selectRaw('DATE(created_at) as date, COUNT(*) as count')
            //           ->where('company_id', $companyId)
            //           ->groupByRaw('DATE(created_at)')
            //           ->orderByRaw('DATE(created_at)')
            
            // Add composite index on company_id and created_at for efficient filtering and date extraction
            $table->index(['company_id', 'created_at'], 'idx_vehicle_bookings_company_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->dropIndex('idx_booking_rooms_company_created');
        });

        Schema::table('vehicle_bookings', function (Blueprint $table) {
            $table->dropIndex('idx_vehicle_bookings_company_created');
        });
    }
};
