<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id('package_id');

            $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
            $table->foreignId('receptionist_id')->constrained('users', 'user_id')->cascadeOnDelete();

            $table->string('package_name');
            $table->string('nama_pengirim');
            $table->string('nama_penerima');

            $table->enum('penyimpanan', ['rak1', 'rak2', 'rak3'])->nullable();
            $table->dateTime('pengambilan')->nullable();
            $table->enum('status', ['stored', 'taken'])->default('stored');
            $table->timestamps();
        });
    }

  
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
