<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')->whereNotNull('department_id')->get(['user_id', 'department_id']);
        foreach ($users as $u) {
            DB::table('user_departments')->updateOrInsert(
                ['user_id' => $u->user_id, 'department_id' => $u->department_id],
                []
            );
        }
    }

    public function down(): void
    {
        DB::table('user_departments')->truncate();
    }
};
