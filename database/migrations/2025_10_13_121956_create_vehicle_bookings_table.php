<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::dropIfExists('vehicle_bookings');

        Schema::create('vehicle_bookings', function (Blueprint $table) {
            $table->id('vehiclebooking_id');
    
            $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->onDelete('cascade');        
            $table->foreignId('company_id')->constrained('companies', 'company_id')->onDelete('cascade');
            $table->foreignId('department_id')->constrained('departments', 'department_id')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users', 'user_id')->onDelete('set null'); 
            
            $table->string('borrower_name')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('purpose');
            $table->string('destination')->nullable();

            $table->enum('odd_even_area', ['tidak', 'ganjil', 'genap'])->default('tidak');
            $table->enum('purpose_type', ['dinas', 'operasional', 'antar_jemput', 'lainnya'])->default('dinas');
            
            $table->boolean('terms_agreed')->default(false);
            $table->boolean('has_sim_a')->default(false);
            
           
            $table->enum('status', [
                'pending', 
                'approved', 
                'on_progress',
                'returned', 
                'completed', 
                'rejected', 
                'cancelled'
            ])->default('pending');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes(); 
        });
    }

  
    public function down(): void
    {
        Schema::dropIfExists('vehicle_bookings');
    }
};