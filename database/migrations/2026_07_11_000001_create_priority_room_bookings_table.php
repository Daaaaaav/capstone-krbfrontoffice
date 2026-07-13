<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priority_room_bookings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
            $table->unsignedBigInteger('manager_id');   // the manager who created it
            $table->foreign('manager_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('room_id');
            $table->foreign('room_id')->references('room_id')->on('rooms')->cascadeOnDelete();

            // Booking details
            $table->string('meeting_title');
            $table->date('date');
            $table->string('start_time', 10);   // HH:MM
            $table->string('end_time', 10);     // HH:MM
            $table->unsignedSmallInteger('number_of_attendees')->default(1);
            $table->text('special_notes')->nullable();

            // Status: pending_receipt (just created) | pending_cancellation (waiting receptionist approval to cancel conflicting booking)
            //         approved | rejected | cancelled_conflict_denied (receptionist denied the cancellation request)
            $table->string('status', 40)->default('pending_receipt');

            // If this priority booking wants to cancel an existing offline booking
            $table->unsignedBigInteger('cancels_booking_id')->nullable(); // FK to booking_rooms.bookingroom_id
            $table->foreign('cancels_booking_id')->references('bookingroom_id')->on('booking_rooms')->nullOnDelete();

            // Who handled this (receptionist who approved/denied)
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->foreign('handled_by')->references('user_id')->on('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priority_room_bookings');
    }
};
