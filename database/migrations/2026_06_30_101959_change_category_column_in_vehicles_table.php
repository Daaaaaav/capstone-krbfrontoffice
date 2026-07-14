<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the enum constraint by changing the column to a plain varchar
        DB::statement("ALTER TABLE `vehicles` MODIFY `category` VARCHAR(100) NOT NULL");
    }

    public function down(): void
    {
        // Restore the original enum (any rows with values outside the enum will be truncated back to '')
        DB::statement("ALTER TABLE `vehicles` MODIFY `category` ENUM('car','pickup','motorcycle','other') NOT NULL");
    }
};
