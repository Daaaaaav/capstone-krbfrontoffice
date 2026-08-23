<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('krb_knowledge_sources', function (Blueprint $table) {
            $table->id('source_id');
            $table->string('name');
            $table->string('type')->default('curated_dataset'); // curated_dataset, document, official_guide
            $table->string('trust_level')->default('verified'); // verified, official, internal
            $table->string('approval_status')->default('approved'); // approved, pending, archived
            $table->string('source_reference')->nullable(); // e.g. "BRIN Botanical Guide 2024", "KRB Official History"
            $table->date('publication_date')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('krb_knowledge_documents', function (Blueprint $table) {
            $table->id('document_id');
            $table->foreignId('source_id')->nullable()->constrained('krb_knowledge_sources', 'source_id')->nullOnDelete();
            $table->string('category'); // history, collections, botany, conservation, research, education, tourism, facilities, services, landmarks, other
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('summary')->nullable();
            $table->longText('content');
            $table->json('keywords')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
            $table->index('slug');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krb_knowledge_documents');
        Schema::dropIfExists('krb_knowledge_sources');
    }
};

