<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id('vehicle_id'); 
            $table->foreignId('company_id')->constrained('companies', 'company_id')->onDelete('cascade');
            $table->string('name');
            $table->enum('category', ['car', 'pickup', 'motorcycle', 'other']);
            $table->string('plate_number', 32)->unique();
            $table->string('year', 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes(); 
        });
    }

  
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};