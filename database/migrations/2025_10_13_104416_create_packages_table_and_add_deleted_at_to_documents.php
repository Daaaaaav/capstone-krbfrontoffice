<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('packages')) {
            Schema::create('packages', function (Blueprint $table) {
                $table->id('package_id');
                $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
                $table->foreignId('receptionist_id')->constrained('users', 'user_id')->cascadeOnDelete();

                $table->enum('type', ['package', 'document', 'invoice', 'etc'])->default('package');
                $table->string('item_name');
                $table->string('nama_pengirim')->nullable();
                $table->string('nama_penerima')->nullable();

                $table->string('penyimpanan', 50)->nullable();
                $table->dateTime('pengambilan')->nullable();
                $table->dateTime('pengiriman')->nullable();
                $table->enum('status', ['pending', 'stored', 'taken', 'delivered'])->default('pending');

                $table->timestamps();
                $table->softDeletes();
                $table->index('deleted_at', 'packages_deleted_at_index');
            });
        }

        if (Schema::hasTable('documents') && !Schema::hasColumn('documents', 'deleted_at')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
                $table->index('deleted_at', 'documents_deleted_at_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'deleted_at')) {
            Schema::table('documents', function (Blueprint $table) {
                if (Schema::hasColumn('documents', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
