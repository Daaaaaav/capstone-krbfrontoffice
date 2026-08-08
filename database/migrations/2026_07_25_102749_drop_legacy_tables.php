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
     Schema::dropIfExists('ticket_comment_reads');
     Schema::dropIfExists('ticket_attachments');
     Schema::dropIfExists('ticket_comments');
     Schema::dropIfExists('ticket_assignments');
     Schema::dropIfExists('tickets');
     Schema::dropIfExists('announcements');
     Schema::dropIfExists('information');
     Schema::dropIfExists('wifis');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
