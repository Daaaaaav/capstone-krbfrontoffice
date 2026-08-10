<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Guestbook;
use Illuminate\Support\Facades\DB;

class DiagnoseGuestbookEncoding extends Command
{
    protected $signature = 'guestbook:diagnose-encoding';
    protected $description = 'Diagnose encoding issues in guestbook data';

    public function handle()
    {
        $this->info('Checking database connection encoding...');
        $charset = DB::connection()->getConfig('charset');
        $collation = DB::connection()->getConfig('collation');
        $this->info("Charset: {$charset}");
        $this->info("Collation: {$collation}");

        $this->info("\nChecking table encoding...");
        $tableStatus = DB::select("SHOW TABLE STATUS WHERE Name = 'guestbooks'");
        if (!empty($tableStatus)) {
            $this->info("Table Collation: " . $tableStatus[0]->Collation);
        }

        $this->info("\nChecking column encodings...");
        $columns = DB::select("SHOW FULL COLUMNS FROM guestbooks WHERE Field IN ('name', 'instansi', 'keperluan', 'petugas_penjaga')");
        foreach ($columns as $col) {
            $this->info("Column '{$col->Field}': Collation = {$col->Collation}");
        }

        $this->info("\nChecking sample data for mojibake patterns...");
        $samples = Guestbook::whereNotNull('name')
            ->take(10)
            ->get(['guestbook_id', 'name', 'instansi', 'keperluan', 'petugas_penjaga']);

        $mojibakePatterns = ['Ã', 'Â', 'â', 'ð', 'Ñ', 'Ã©', 'Ã§', 'Ã­'];
        $affectedCount = 0;
        $affectedIds = [];

        foreach ($samples as $gb) {
            $hasMojibake = false;
            $fields = [];

            foreach (['name', 'instansi', 'keperluan', 'petugas_penjaga'] as $field) {
                $value = $gb->$field;
                if ($value) {
                    foreach ($mojibakePatterns as $pattern) {
                        if (str_contains($value, $pattern)) {
                            $hasMojibake = true;
                            $fields[] = $field;
                            break;
                        }
                    }
                }
            }

            if ($hasMojibake) {
                $affectedCount++;
                $affectedIds[] = $gb->guestbook_id;
                $this->warn("ID {$gb->guestbook_id} has mojibake in: " . implode(', ', array_unique($fields)));
                $this->line("  Name: {$gb->name}");
                if ($gb->instansi) $this->line("  Instansi: {$gb->instansi}");
                if ($gb->keperluan) $this->line("  Keperluan: {$gb->keperluan}");
            }
        }

        if ($affectedCount === 0) {
            $this->info("\n✓ No mojibake detected in the sampled records.");
        } else {
            $this->warn("\n✗ Found {$affectedCount} records with mojibake in the sample.");
            $this->info("Affected IDs: " . implode(', ', $affectedIds));
            
            $this->info("\nChecking total affected records...");
            $totalAffected = Guestbook::where(function($q) use ($mojibakePatterns) {
                foreach ($mojibakePatterns as $pattern) {
                    $q->orWhere('name', 'like', "%{$pattern}%")
                      ->orWhere('instansi', 'like', "%{$pattern}%")
                      ->orWhere('keperluan', 'like', "%{$pattern}%")
                      ->orWhere('petugas_penjaga', 'like', "%{$pattern}%");
                }
            })->count();
            
            $this->warn("Total records potentially affected: {$totalAffected}");
        }

        return 0;
    }
}
