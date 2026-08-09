<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('guestbooks', function (Blueprint $table) {
            // kolom user_id nullable, sesuaikan tipe dengan users.user_id (bigint unsigned)
            $table->unsignedBigInteger('user_id')
                  ->nullable()
                  ->after('department_id');

            // foreign key ke tabel users, kolom user_id
            $table->foreign('user_id', 'guestbooks_user_id_foreign')
                  ->references('user_id')
                  ->on('users')
                  ->nullOnDelete(); // ON DELETE SET NULL
        });
    }

  
    public function down(): void
    {
        Schema::table('guestbooks', function (Blueprint $table) {
            // hapus dulu FK, lalu kolomnya
            $table->dropForeign('guestbooks_user_id_foreign');
            $table->dropColumn('user_id');
        });
    }
};
