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
        Schema::create('wazuh_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('rule_id')->nullable();
            $table->unsignedTinyInteger('rule_level')->default(0);
            $table->text('description');
            $table->string('agent_name')->nullable();
            $table->longText('raw_log')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wazuh_alerts');
    }
};
