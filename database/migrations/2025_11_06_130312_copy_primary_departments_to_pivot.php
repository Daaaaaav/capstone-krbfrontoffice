<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            INSERT INTO user_departments (user_id, department_id)
            SELECT user_id, department_id
            FROM users
            WHERE department_id IS NOT NULL
            ON DUPLICATE KEY UPDATE user_id = users.user_id
        ');
    }

    public function down(): void
    {
        DB::table('user_departments')->truncate();
    }
};
