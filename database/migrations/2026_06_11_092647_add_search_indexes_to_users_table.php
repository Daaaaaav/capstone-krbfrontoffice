<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add indexes on columns used in the receptionist users search query.
     * This speeds up LIKE '%...%' prefix scans and the role/company joins.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Used in WHERE full_name LIKE '%...%'
            $table->index('full_name', 'users_full_name_index');

            // phone_number LIKE searches
            $table->index('phone_number', 'users_phone_number_index');

            // Frequently filtered together in the receptionist query
            $table->index(['company_id', 'role_id'], 'users_company_role_index');
        });
    }

    public function down(): void
    {
        $dbName  = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();

        // Check which of our indexes exist
        $indexes = \Illuminate\Support\Facades\DB::select(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users'",
            [$dbName]
        );
        $existing = collect($indexes)->pluck('INDEX_NAME')->unique()->all();

        // Check whether any FK depends on users_company_role_index
        $fkBlocking = \Illuminate\Support\Facades\DB::select(
            "SELECT COUNT(*) as cnt
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.TABLE_CONSTRAINTS tc
               ON tc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
              AND tc.TABLE_SCHEMA    = k.TABLE_SCHEMA
              AND tc.TABLE_NAME      = k.TABLE_NAME
             WHERE k.TABLE_SCHEMA        = ?
               AND k.TABLE_NAME          = 'users'
               AND tc.CONSTRAINT_TYPE    = 'FOREIGN KEY'
               AND k.COLUMN_NAME        IN ('company_id', 'role_id')",
            [$dbName]
        );
        $hasFkBlocking = ($fkBlocking[0]->cnt ?? 0) > 0;

        Schema::table('users', function (Blueprint $table) use ($existing, $hasFkBlocking) {
            if (in_array('users_full_name_index',    $existing, true)) {
                $table->dropIndex('users_full_name_index');
            }
            if (in_array('users_phone_number_index', $existing, true)) {
                $table->dropIndex('users_phone_number_index');
            }
            // Only drop the composite index if no FK is using it as its backing index
            if (!$hasFkBlocking && in_array('users_company_role_index', $existing, true)) {
                $table->dropIndex('users_company_role_index');
            }
        });
    }
};
