<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
            $table->index('deleted_at');

            $table->dropUnique('users_email_unique'); 
            $table->unique(['email', 'deleted_at'], 'users_email_deleted_at_unique');
        });
    }

    public function down(): void
    {
        if (\DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', \DB::getDatabaseName())->where('TABLE_NAME', 'users')->where('INDEX_NAME', 'users_email_deleted_at_unique')->exists()) {
            \DB::statement('ALTER TABLE `users` DROP INDEX `users_email_deleted_at_unique`');
        }

        \DB::statement('
            DELETE u1 FROM users u1
            INNER JOIN users u2
            ON u1.email = u2.email AND u1.user_id < u2.user_id
        ');

        if (!\DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', \DB::getDatabaseName())->where('TABLE_NAME', 'users')->where('INDEX_NAME', 'users_email_unique')->exists()) {
            \DB::statement('ALTER TABLE `users` ADD UNIQUE INDEX `users_email_unique` (`email`)');
        }

        if (\DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', \DB::getDatabaseName())->where('TABLE_NAME', 'users')->where('INDEX_NAME', 'users_deleted_at_index')->exists()) {
            \DB::statement('ALTER TABLE `users` DROP INDEX `users_deleted_at_index`');
        }
        if (\DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', \DB::getDatabaseName())->where('TABLE_NAME', 'users')->where('COLUMN_NAME', 'deleted_at')->exists()) {
            \DB::statement('ALTER TABLE `users` DROP COLUMN `deleted_at`');
        }
    }
};
