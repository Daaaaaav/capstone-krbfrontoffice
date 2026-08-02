<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();

            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->foreign('recipient_id')->references('user_id')->on('users')->nullOnDelete();

            $table->string('type', 80);   // notification type: 'priority_room_cancel_request' | 'priority_vehicle_cancel_request' | 'priority_room_approved' | etc.
            $table->string('title');
            $table->text('message');
            $table->string('notifiable_type')->nullable(); 
            $table->unsignedBigInteger('notifiable_id')->nullable();

            $table->boolean('action_required')->default(false);
            $table->string('action_taken', 20)->nullable(); // 'approved' | 'denied' | null (default)

            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'is_read']);
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_notifications');
    }
};
