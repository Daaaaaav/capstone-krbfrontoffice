<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('vehicle_booking_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiclebooking_id')->constrained('vehicle_bookings', 'vehiclebooking_id')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->enum('photo_type', ['before', 'after']);
            
            // PERBAIKAN: Menggunakan 'photo_path' untuk local storage
            // Menghapus 'cloudinary_public_id' sesuai permintaan
            $table->string('photo_path', 1024); // Path ke file di local storage

            $table->timestamps();
            // Tidak ada softDeletes di SQL asli, jadi kita ikuti
        });
    }

  
    public function down(): void
    {
        Schema::dropIfExists('vehicle_booking_photos');
    }
};