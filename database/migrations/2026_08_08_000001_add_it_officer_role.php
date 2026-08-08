<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if IT Officer role already exists
        $exists = DB::table('roles')->where('name', 'IT Officer')->exists();
        
        if (!$exists) {
            DB::table('roles')->insert([
                'name' => 'IT Officer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Remove IT Officer role
        DB::table('roles')->where('name', 'IT Officer')->delete();
    }
};
