<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('requires_early_approval')
                  ->default(true)
                  ->after('capacity')
                  ->comment('When true, offline bookings for this room must be created at least 1 hour before start.');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('requires_early_approval');
        });
    }
};
