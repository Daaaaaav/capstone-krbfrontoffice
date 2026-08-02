<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments', 'department_id')->nullOnDelete();
            $table->foreignId('role_id')->constrained('roles', 'role_id')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone_number', 30);
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
