<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priority_vehicle_bookings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
            $table->unsignedBigInteger('manager_id');
            $table->foreign('manager_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('vehicle_id');
            $table->foreign('vehicle_id')->references('vehicle_id')->on('vehicles')->cascadeOnDelete();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreign('department_id')->references('department_id')->on('departments')->nullOnDelete();

            $table->string('borrower_name');
            $table->datetime('start_at');
            $table->datetime('end_at');
            $table->string('purpose');
            $table->string('destination')->nullable();
            $table->string('purpose_type', 50)->nullable();
            $table->text('special_notes')->nullable();

            $table->string('status', 40)->default('pending_receipt');
            $table->unsignedBigInteger('cancels_booking_id')->nullable(); 
            $table->foreign('cancels_booking_id')->references('vehiclebooking_id')->on('vehicle_bookings')->nullOnDelete();

            $table->unsignedBigInteger('handled_by')->nullable();
            $table->foreign('handled_by')->references('user_id')->on('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priority_vehicle_bookings');
    }
};
