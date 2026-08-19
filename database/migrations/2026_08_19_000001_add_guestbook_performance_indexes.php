<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add indexes to optimize Guestbook lookups, autocompletion search,
     * and status/upcoming visitor queries.
     */
    public function up(): void
    {
        Schema::table('guestbooks', function (Blueprint $table) {
            // Index for autocomplete: where('name', 'like', ...)->where('company_id', ...)->latest('created_at')
            $table->index(['company_id', 'name', 'created_at'], 'idx_guestbooks_company_name_created');

            // Index for active & upcoming status queries: where('company_id', ...)->whereNull('jam_out')->where('date', ...)
            $table->index(['company_id', 'date', 'jam_out'], 'idx_guestbooks_company_date_jamout');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guestbooks', function (Blueprint $table) {
            $table->dropIndex('idx_guestbooks_company_name_created');
            $table->dropIndex('idx_guestbooks_company_date_jamout');
        });
    }
};
