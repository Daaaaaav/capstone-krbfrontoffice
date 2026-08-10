<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Guestbook;
use Illuminate\Support\Facades\DB;

class RepairGuestbookEncoding extends Command
{
    protected $signature = 'guestbook:repair-encoding {--dry-run : Preview changes without applying them} {--limit=50 : Maximum number of records to repair}';
    protected $description = 'Repair mojibake encoding issues in guestbook records (UTF-8 double-encoding fix)';

    private array $mojibakeMap = [
        // Common UTF-8 double-encoding patterns
        'Ã ' => 'à', 'Ã¡' => 'á', 'Ã¢' => 'â', 'Ã£' => 'ã', 'Ã¤' => 'ä', 'Ã¥' => 'å',
        'Ã¨' => 'è', 'Ã©' => 'é', 'Ãª' => 'ê', 'Ã«' => 'ë',
        'Ã¬' => 'ì', 'Ã­' => 'í', 'Ã®' => 'î', 'Ã¯' => 'ï',
        'Ã²' => 'ò', 'Ã³' => 'ó', 'Ã´' => 'ô', 'Ãµ' => 'õ', 'Ã¶' => 'ö',
        'Ã¹' => 'ù', 'Ãº' => 'ú', 'Ã»' => 'û', 'Ã¼' => 'ü',
        'Ã§' => 'ç', 'Ã±' => 'ñ',
        'Ã‰' => 'É', 'Ã' => 'Í',
        'â€"' => '–', 'â€"' => '—', 'â€˜' => ''', 'â€™' => ''',
        'â€œ' => '"', 'â€' => '"', 'â€¢' => '•',
        // Additional common patterns
        'Â ' => ' ', // Non-breaking space
        'Â·' => '·', // Middle dot
        'Ã€' => 'À', 'Ã�' => 'Á', 'Ã‚' => 'Â', 'Ãƒ' => 'Ã', 'Ã„' => 'Ä', 'Ã…' => 'Å',
        'Ãˆ' => 'È', 'ÃŠ' => 'Ê', 'Ã‹' => 'Ë',
        'ÃŒ' => 'Ì', 'ÃŽ' => 'Î', 'Ã�' => 'Ï',
        'Ã'' => 'Ò', 'Ã"' => 'Ó', 'Ã"' => 'Ô', 'Ã•' => 'Õ', 'Ã–' => 'Ö',
        'Ã™' => 'Ù', 'Ãš' => 'Ú', 'Ã›' => 'Û', 'Ãœ' => 'Ü',
        'Ã‡' => 'Ç', 'Ã'' => 'Ñ',
    ];

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info('Starting Guestbook Encoding Repair...');
        $this->info('Mode: ' . ($isDryRun ? 'DRY RUN (no changes will be saved)' : 'LIVE (changes will be saved)'));
        $this->newLine();

        // Find records with mojibake patterns
        $mojibakePatterns = array_keys($this->mojibakeMap);
        
        $query = Guestbook::query();
        foreach ($mojibakePatterns as $i => $pattern) {
            if ($i === 0) {
                $query->where(function($q) use ($pattern) {
                    $q->where('name', 'like', "%{$pattern}%")
                      ->orWhere('instansi', 'like', "%{$pattern}%")
                      ->orWhere('keperluan', 'like', "%{$pattern}%")
                      ->orWhere('petugas_penjaga', 'like', "%{$pattern}%");
                });
            } else {
                $query->orWhere(function($q) use ($pattern) {
                    $q->where('name', 'like', "%{$pattern}%")
                      ->orWhere('instansi', 'like', "%{$pattern}%")
                      ->orWhere('keperluan', 'like', "%{$pattern}%")
                      ->orWhere('petugas_penjaga', 'like', "%{$pattern}%");
                });
            }
            
            // Limit search patterns for performance
            if ($i >= 10) break;
        }

        $affectedRecords = $query->limit($limit)->get();

        if ($affectedRecords->isEmpty()) {
            $this->info('✓ No records with mojibake detected.');
            return 0;
        }

        $this->warn("Found {$affectedRecords->count()} records with potential mojibake.");
        $this->newLine();

        $repairedCount = 0;
        $changes = [];

        foreach ($affectedRecords as $record) {
            $hasChanges = false;
            $recordChanges = [
                'id' => $record->guestbook_id,
                'before' => [],
                'after' => [],
            ];

            foreach (['name', 'instansi', 'keperluan', 'petugas_penjaga'] as $field) {
                $original = $record->$field;
                if (!$original) continue;

                $repaired = $this->repairText($original);
                
                if ($repaired !== $original) {
                    $hasChanges = true;
                    $recordChanges['before'][$field] = $original;
                    $recordChanges['after'][$field] = $repaired;

                    if (!$isDryRun) {
                        $record->$field = $repaired;
                    }
                }
            }

            if ($hasChanges) {
                $repairedCount++;
                $changes[] = $recordChanges;

                // Display changes
                $this->line("<fg=cyan>Record ID: {$record->guestbook_id}</>");
                foreach ($recordChanges['before'] as $field => $beforeValue) {
                    $afterValue = $recordChanges['after'][$field];
                    $this->line("  <fg=yellow>{$field}:</>");
                    $this->line("    <fg=red>Before:</>  {$beforeValue}");
                    $this->line("    <fg=green>After:</>   {$afterValue}");
                }
                $this->newLine();

                if (!$isDryRun) {
                    $record->save();
                }
            }
        }

        if ($repairedCount === 0) {
            $this->info('✓ No records required repair.');
            return 0;
        }

        $this->newLine();
        $this->info("═══════════════════════════════════════════");
        $this->info("Summary:");
        $this->info("  Total checked: {$affectedRecords->count()}");
        $this->info("  Records repaired: {$repairedCount}");
        
        if ($isDryRun) {
            $this->warn("\n⚠ DRY RUN MODE - No changes were saved to the database.");
            $this->info("Run without --dry-run to apply these changes.");
        } else {
            $this->info("\n✓ Changes have been saved to the database.");
        }

        // Generate repair log
        if (!$isDryRun && $repairedCount > 0) {
            $logFile = storage_path('logs/guestbook_encoding_repair_' . now()->format('Y-m-d_His') . '.json');
            file_put_contents($logFile, json_encode($changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Repair log saved to: {$logFile}");
        }

        return 0;
    }

    private function repairText(string $text): string
    {
        // Replace mojibake patterns with correct UTF-8 characters
        $repaired = str_replace(
            array_keys($this->mojibakeMap),
            array_values($this->mojibakeMap),
            $text
        );

        return $repaired;
    }
}
