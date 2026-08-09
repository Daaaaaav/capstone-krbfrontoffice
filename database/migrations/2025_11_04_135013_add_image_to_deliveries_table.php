<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // tambah kolom image (path / filename), boleh null
            $table->string('image')->nullable()->after('direction');
            // kalau mau di posisi lain, ganti 'direction' dengan nama kolom sebelumnya
        });
    }

  
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
