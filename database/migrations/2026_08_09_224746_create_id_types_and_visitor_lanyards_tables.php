<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('id_types', function (Blueprint $table) {
            $table->id();
            $table->string('id_type_name');
            $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('visitor_lanyards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
            $table->string('lanyard_name');
            $table->boolean('status')->default(1); // 1 = available, 0 = unavailable
            $table->timestamps();
        });

        Schema::table('guestbooks', function (Blueprint $table) {
            $table->foreignId('id_type_id')->nullable()->constrained('id_types')->nullOnDelete();
            $table->foreignId('visitor_lanyard_id')->nullable()->constrained('visitor_lanyards')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guestbooks', function (Blueprint $table) {
            $table->dropForeign(['id_type_id']);
            $table->dropForeign(['visitor_lanyard_id']);
            $table->dropColumn(['id_type_id', 'visitor_lanyard_id']);
        });

        Schema::dropIfExists('visitor_lanyards');
        Schema::dropIfExists('id_types');
    }
};
