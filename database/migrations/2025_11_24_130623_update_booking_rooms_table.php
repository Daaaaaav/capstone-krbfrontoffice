<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateBookingRoomsTable extends Migration
{
    public function up()
    {
        Schema::table('booking_rooms', function (Blueprint $table) {
            // Update the ENUM field 'requestinformation' to include 'rejected' and make it nullable
            $table->enum('requestinformation', ['request', 'inform','rejected'])
                  ->nullable()
                  ->default(null)  // Default set to null
                  ->change();

            // Add a new text field 'noterequest'
            $table->text('noterequest')->nullable()->after('requestinformation');
        });
    }

    public function down()
    {
        Schema::table('booking_rooms', function (Blueprint $table) {
            // Revert the ENUM field 'requestinformation' to its original state and make it nullable
            $table->enum('requestinformation', ['request', 'inform'])
                  ->nullable()
                  ->default(null)  // Default set to null
                  ->change();

            // Drop the 'noterequest' column
            $table->dropColumn('noterequest');
        });
    }
}
