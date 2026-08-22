<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::dropIfExists('guestbooks');
        Schema::create('guestbooks', function (Blueprint $table) {
            $table->bigIncrements('guestbook_id');
            $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
            $table->date('date');
            $table->time('jam_in');
            $table->time('jam_out')->nullable();
            $table->string('name');
            $table->string('phone_number')->nullable();
            $table->string('instansi')->nullable();
            $table->string('keperluan');
        $table->string('petugas_penjaga')->nullable();

            $table->timestamps();
        });

    }


    public function down(): void
    {
        Schema::dropIfExists('guestbooks');
    }
};
