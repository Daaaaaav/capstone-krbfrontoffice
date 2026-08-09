<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->bigIncrements('document_id'); 
            $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
            $table->foreignId('receptionist_id')->constrained('users', 'user_id')->cascadeOnDelete(); 
            $table->string('document_name');
            $table->string('nama_pengirim')->nullable();
            $table->string('nama_penerima')->nullable();
            $table->enum('type', ['document','invoice','etc'])->default('document');
            $table->string('penyimpanan', 50);
            $table->dateTime('pengambilan')->nullable();
            $table->dateTime('pengiriman')->nullable();
            $table->enum('status', ['pending','taken','delivered'])->default('pending'); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
