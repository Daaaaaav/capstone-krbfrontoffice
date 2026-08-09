<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        // === Add to guestbooks table ===
        Schema::table('guestbooks', function (Blueprint $table) {
            if (!Schema::hasColumn('guestbooks', 'department_id')) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('departments', 'department_id')
                    ->cascadeOnDelete();
            }
        });

        // === Add to deliveries table ===
        Schema::table('deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('deliveries', 'department_id')) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('departments', 'department_id')
                    ->cascadeOnDelete();
            }
        });
    }

  
    public function down(): void
    {
        Schema::table('guestbooks', function (Blueprint $table) {
            if (Schema::hasColumn('guestbooks', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }
        });

        Schema::table('deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('deliveries', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }
        });
    }
};
