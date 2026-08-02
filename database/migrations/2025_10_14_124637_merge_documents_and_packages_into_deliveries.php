<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('storages')) {
            Schema::create('storages', function (Blueprint $table) {
                $table->id('storage_id');
                if (Schema::hasTable('companies')) {
                    $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
                } else {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                }

                $table->string('code', 100)->index(); 
                $table->string('name', 150)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'code']);
            });
        }

        if (!Schema::hasTable('deliveries')) {
            Schema::create('deliveries', function (Blueprint $table) {
                $table->id('delivery_id');

                if (Schema::hasTable('companies')) {
                    $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
                } else {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                }

                $table->unsignedBigInteger('receptionist_id')->nullable()->index();
                $table->string('item_name', 255);
                $table->enum('type', ['document', 'invoice', 'etc', 'package'])->default('document')->index();
                $table->string('nama_pengirim', 255)->nullable();
                $table->string('nama_penerima', 255)->nullable();
                $table->foreignId('storage_id')->nullable()->constrained('storages', 'storage_id')->nullOnDelete();
                $table->dateTime('pengambilan')->nullable();
                $table->dateTime('pengiriman')->nullable();
                $table->enum('status', ['pending', 'stored', 'taken', 'delivered'])->default('pending')->index();

                $table->timestamps();
                $table->softDeletes();
            });
        }

        $storageRows = [];

        if (Schema::hasTable('documents')) {
            $docs = DB::table('documents')->select('company_id', 'penyimpanan')->whereNotNull('penyimpanan')->get();
            foreach ($docs as $d) {
                $code = trim(strtolower((string)$d->penyimpanan));
                if ($code !== '') {
                    $storageRows[$d->company_id . '|' . $code] = ['company_id' => $d->company_id, 'code' => $code];
                }
            }
        }

        if (Schema::hasTable('packages')) {
            $packs = DB::table('packages')->select('company_id', 'penyimpanan')->whereNotNull('penyimpanan')->get();
            foreach ($packs as $p) {
                $code = trim(strtolower((string)$p->penyimpanan));
                if ($code !== '') {
                    $storageRows[$p->company_id . '|' . $code] = ['company_id' => $p->company_id, 'code' => $code];
                }
            }
        }

        // Upsert ke storages (unique company_id+code)
        foreach ($storageRows as $row) {
            $existsId = DB::table('storages')
                ->where('company_id', $row['company_id'])
                ->where('code', $row['code'])
                ->value('storage_id');

            if (!$existsId) {
                DB::table('storages')->insert([
                    'company_id' => $row['company_id'],
                    'code'       => $row['code'],
                    'name'       => strtoupper($row['code']),
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Helper closure untuk cari storage_id dari (company_id, code)
        $getStorageId = function ($companyId, $code) {
            if ($code === null) return null;
            $key = trim(strtolower((string)$code));
            if ($key === '') return null;
            return DB::table('storages')
                ->where('company_id', $companyId)
                ->where('code', $key)
                ->value('storage_id');
        };

        // 4) Migrasi data: documents -> deliveries
        if (Schema::hasTable('documents')) {
            DB::table('documents')->orderBy('document_id')->lazy()->each(function ($doc) use ($getStorageId) {
                // doc->type bisa 'document','invoice','etc' -> kita pertahankan nilainya.
                $type = in_array($doc->type, ['document', 'invoice', 'etc'], true) ? $doc->type : 'document';

                DB::table('deliveries')->insert([
                    'company_id'      => $doc->company_id,
                    'receptionist_id' => $doc->receptionist_id,
                    'item_name'       => $doc->document_name,
                    'type'            => $type,
                    'nama_pengirim'   => $doc->nama_pengirim,
                    'nama_penerima'   => $doc->nama_penerima,
                    'storage_id'      => $getStorageId($doc->company_id, $doc->penyimpanan),
                    'pengambilan'     => $doc->pengambilan,
                    'pengiriman'      => $doc->pengiriman,
                    // status di documents: pending/taken/delivered -> valid di enum baru
                    'status'          => in_array($doc->status, ['pending', 'taken', 'delivered'], true) ? $doc->status : 'pending',
                    'created_at'      => $doc->created_at,
                    'updated_at'      => $doc->updated_at,
                    'deleted_at'      => $doc->deleted_at ?? null,
                ]);
            });
        }

        // 5) Migrasi data: packages -> deliveries (type = 'package')
        if (Schema::hasTable('packages')) {
            DB::table('packages')->orderBy('package_id')->lazy()->each(function ($pkg) use ($getStorageId) {
                // status di packages: stored/taken
                $status = in_array($pkg->status, ['stored', 'taken'], true) ? $pkg->status : 'stored';

                DB::table('deliveries')->insert([
                    'company_id'      => $pkg->company_id,
                    'receptionist_id' => $pkg->receptionist_id,
                    'item_name'       => $pkg->package_name,
                    'type'            => 'package',
                    'nama_pengirim'   => $pkg->nama_pengirim,
                    'nama_penerima'   => $pkg->nama_penerima,
                    'storage_id'      => $getStorageId($pkg->company_id, $pkg->penyimpanan),
                    'pengambilan'     => $pkg->pengambilan,
                    'pengiriman'      => null, // di packages tidak ada kolom ini
                    'status'          => $status,
                    'created_at'      => $pkg->created_at,
                    'updated_at'      => $pkg->updated_at,
                    'deleted_at'      => $pkg->deleted_at ?? null,
                ]);
            });
        }

        // 6) Hapus tabel lama
        if (Schema::hasTable('packages')) {
            Schema::drop('packages');
        }
        if (Schema::hasTable('documents')) {
            Schema::drop('documents');
        }
    }

    public function down(): void
    {
        // Balikkan: buat lagi documents & packages, lalu pindahkan dari deliveries
        // 1) Recreate documents
        if (!Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
                $table->id('document_id');
                if (Schema::hasTable('companies')) {
                    $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
                } else {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                }
                $table->unsignedBigInteger('receptionist_id')->nullable()->index();
                $table->string('document_name', 255);
                $table->string('nama_pengirim', 255)->nullable();
                $table->string('nama_penerima', 255)->nullable();
                $table->enum('type', ['document', 'invoice', 'etc'])->default('document');
                $table->string('penyimpanan', 50)->nullable();
                $table->dateTime('pengambilan')->nullable();
                $table->dateTime('pengiriman')->nullable();
                $table->enum('status', ['pending', 'taken', 'delivered'])->default('pending');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2) Recreate packages
        if (!Schema::hasTable('packages')) {
            Schema::create('packages', function (Blueprint $table) {
                $table->id('package_id');
                if (Schema::hasTable('companies')) {
                    $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
                } else {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                }
                $table->unsignedBigInteger('receptionist_id')->nullable()->index();
                $table->string('package_name', 255);
                $table->string('nama_pengirim', 255)->nullable();
                $table->string('nama_penerima', 255)->nullable();
                $table->string('penyimpanan', 50)->nullable();
                $table->dateTime('pengambilan')->nullable();
                $table->enum('status', ['stored', 'taken'])->default('stored');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 3) Pindahkan balik dari deliveries
        if (Schema::hasTable('deliveries')) {
            // Ambil map storage_id -> code untuk mengembalikan "penyimpanan"
            $storageMap = DB::table('storages')->pluck('code', 'storage_id'); // [storage_id => code]

            DB::table('deliveries')->orderBy('delivery_id')->lazy()->each(function ($d) use ($storageMap) {
                $code = $d->storage_id ? ($storageMap[$d->storage_id] ?? null) : null;

                if ($d->type === 'package') {
                    DB::table('packages')->insert([
                        'company_id'      => $d->company_id,
                        'receptionist_id' => $d->receptionist_id,
                        'package_name'    => $d->item_name,
                        'nama_pengirim'   => $d->nama_pengirim,
                        'nama_penerima'   => $d->nama_penerima,
                        'penyimpanan'     => $code,
                        'pengambilan'     => $d->pengambilan,
                        'status'          => in_array($d->status, ['stored', 'taken'], true) ? $d->status : 'stored',
                        'created_at'      => $d->created_at,
                        'updated_at'      => $d->updated_at,
                        'deleted_at'      => $d->deleted_at,
                    ]);
                } else {
                    DB::table('documents')->insert([
                        'company_id'      => $d->company_id,
                        'receptionist_id' => $d->receptionist_id,
                        'document_name'   => $d->item_name,
                        'nama_pengirim'   => $d->nama_pengirim,
                        'nama_penerima'   => $d->nama_penerima,
                        'type'            => in_array($d->type, ['document', 'invoice', 'etc'], true) ? $d->type : 'document',
                        'penyimpanan'     => $code,
                        'pengambilan'     => $d->pengambilan,
                        'pengiriman'      => $d->pengiriman,
                        'status'          => in_array($d->status, ['pending', 'taken', 'delivered'], true) ? $d->status : 'pending',
                        'created_at'      => $d->created_at,
                        'updated_at'      => $d->updated_at,
                        'deleted_at'      => $d->deleted_at,
                    ]);
                }
            });

            // Hapus tabel deliveries (gabungan)
            Schema::drop('deliveries');
        }

        // (Opsional) Biarkan tabel storages tetap ada; kalau mau dibalik juga, hilangkan komentar:
        Schema::dropIfExists('storages');
    }
};
