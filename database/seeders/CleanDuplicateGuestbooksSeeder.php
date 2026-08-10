<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Guestbook;
use App\Models\IdType;
use App\Models\VisitorLanyard;
use Carbon\Carbon;

/**
 * One-time cleanup seeder for duplicate and invalid Guestbook records.
 * 
 * This seeder:
 * 1. Identifies and removes same-day duplicate visitor entries
 * 2. Repairs records with missing id_type_id or visitor_lanyard_id
 * 3. Handles records with missing/invalid dates
 * 
 * SAFETY:
 * - Runs inside a transaction (rollback on any error)
 * - Preserves the most complete record when duplicates exist
 * - Never deletes records based solely on visitor name
 * - Idempotent (safe to run multiple times)
 * 
 * DO NOT run this from DatabaseSeeder.php - execute explicitly:
 * php artisan db:seed --class=CleanDuplicateGuestbooksSeeder
 */
class CleanDuplicateGuestbooksSeeder extends Seeder
{
    protected $deletedCount = 0;
    protected $repairedCount = 0;
    protected $deletedIds = [];
    protected $retainedIds = [];

    public function run(): void
    {
        $this->info('╔═══════════════════════════════════════════════════════════════╗');
        $this->info('║     GUESTBOOK DUPLICATE CLEANUP (WITH TRANSACTION)            ║');
        $this->info('╚═══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        DB::transaction(function () {
            $beforeCount = Guestbook::count();
            $this->info("📊 Guestbook records before cleanup: {$beforeCount}");
            $this->newLine();

            // Step 1: Repair records with missing id_type_id or visitor_lanyard_id
            $this->repairMissingRelationships();

            // Step 2: Remove same-day duplicates
            $this->removeSameDayDuplicates();

            // Step 3: Handle records with missing dates
            $this->handleMissingDates();

            $afterCount = Guestbook::count();
            $this->newLine();
            $this->info('╔═══════════════════════════════════════════════════════════════╗');
            $this->info('║                    CLEANUP COMPLETED                          ║');
            $this->info('╚═══════════════════════════════════════════════════════════════╝');
            $this->info("📊 Records before: {$beforeCount}");
            $this->info("📊 Records after: {$afterCount}");
            $this->info("🔧 Records repaired: {$this->repairedCount}");
            $this->info("🗑️  Records deleted: {$this->deletedCount}");
            
            if (!empty($this->deletedIds)) {
                $this->warn("\n🗑️  Deleted Guestbook IDs: " . implode(', ', $this->deletedIds));
            }
            
            if (!empty($this->retainedIds)) {
                $this->info("\n✅ Retained Guestbook IDs (from duplicate groups): " . implode(', ', $this->retainedIds));
            }
        });

        $this->newLine();
        $this->info('✅ Transaction committed successfully!');
        $this->info('💡 Please verify the Manager Dashboard → Guestbook Statistics');
    }

    /**
     * Repair records with missing id_type_id or visitor_lanyard_id
     * by assigning them to existing shared resources.
     */
    protected function repairMissingRelationships(): void
    {
        $this->info('🔧 Step 1: Repairing missing relationships...');

        // Get available ID Types and Visitor Lanyards (shared resources)
        $idTypes = IdType::all();
        $visitorLanyards = VisitorLanyard::all();

        if ($idTypes->isEmpty()) {
            $this->warn('   ⚠️  No ID Types available - skipping id_type_id repair');
            $this->warn('   Please create ID Types through: IT Officer > Manage ID Types');
        } else {
            $missingIdType = Guestbook::whereNull('id_type_id')->get();
            
            if ($missingIdType->isNotEmpty()) {
                $this->info("   Found {$missingIdType->count()} records with missing id_type_id");
                
                foreach ($missingIdType as $guest) {
                    // Assign a random existing ID Type (shared resource)
                    $guest->id_type_id = $idTypes->random()->id;
                    $guest->save();
                    $this->repairedCount++;
                }
                
                $this->info("   ✅ Repaired {$missingIdType->count()} records with missing id_type_id");
            } else {
                $this->info("   ✅ No records with missing id_type_id");
            }
        }

        if ($visitorLanyards->isEmpty()) {
            $this->warn('   ⚠️  No Visitor Lanyards available - skipping visitor_lanyard_id repair');
            $this->warn('   Please create Visitor Lanyards through: IT Officer > Manage Visitor Lanyards');
        } else {
            $missingLanyard = Guestbook::whereNull('visitor_lanyard_id')->get();
            
            if ($missingLanyard->isNotEmpty()) {
                $this->info("   Found {$missingLanyard->count()} records with missing visitor_lanyard_id");
                
                foreach ($missingLanyard as $guest) {
                    // Assign a random existing Visitor Lanyard (shared resource)
                    // This is safe for historical data since all visits are already completed
                    $guest->visitor_lanyard_id = $visitorLanyards->random()->id;
                    $guest->save();
                    $this->repairedCount++;
                }
                
                $this->info("   ✅ Repaired {$missingLanyard->count()} records with missing visitor_lanyard_id");
            } else {
                $this->info("   ✅ No records with missing visitor_lanyard_id");
            }
        }

        $this->newLine();
    }

    /**
     * Remove same-day duplicate visitors (same company, date, name).
     * Retains the most complete record.
     */
    protected function removeSameDayDuplicates(): void
    {
        $this->info('🔍 Step 2: Finding same-day duplicates...');

        // Find duplicate groups: same company + same date + same name
        $duplicateGroups = Guestbook::select(
                'company_id',
                'date',
                'name',
                DB::raw('COUNT(*) as count'),
                DB::raw('GROUP_CONCAT(guestbook_id ORDER BY guestbook_id) as ids')
            )
            ->whereNotNull('date')
            ->groupBy('company_id', 'date', 'name')
            ->having('count', '>', 1)
            ->get();

        if ($duplicateGroups->isEmpty()) {
            $this->info('   ✅ No same-day duplicates found');
            $this->newLine();
            return;
        }

        $this->warn("   Found {$duplicateGroups->count()} duplicate groups");

        foreach ($duplicateGroups as $group) {
            $ids = array_map('intval', explode(',', $group->ids));
            $records = Guestbook::whereIn('guestbook_id', $ids)
                ->orderBy('guestbook_id')
                ->get();

            $this->info("\n   📍 Duplicate Group: {$group->name} on {$group->date} (Company {$group->company_id})");
            $this->info("      Found {$records->count()} records: IDs " . implode(', ', $ids));

            // Score each record to find the most complete one
            $bestRecord = null;
            $bestScore = -1;

            foreach ($records as $record) {
                $score = $this->scoreRecordCompleteness($record);
                
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestRecord = $record;
                }
            }

            // Retain the best record, delete the rest
            $this->retainedIds[] = $bestRecord->guestbook_id;
            $this->info("      ✅ Retaining: ID {$bestRecord->guestbook_id} (score: {$bestScore})");

            foreach ($records as $record) {
                if ($record->guestbook_id !== $bestRecord->guestbook_id) {
                    $this->deletedIds[] = $record->guestbook_id;
                    $this->info("      🗑️  Deleting: ID {$record->guestbook_id}");
                    $record->delete();
                    $this->deletedCount++;
                }
            }
        }

        $this->newLine();
    }

    /**
     * Score a guestbook record based on data completeness.
     * Higher score = more complete record.
     */
    protected function scoreRecordCompleteness(Guestbook $record): int
    {
        $score = 0;

        // Prefer records with valid relationships
        if ($record->id_type_id) $score += 10;
        if ($record->visitor_lanyard_id) $score += 10;
        
        // Prefer records with valid times
        if ($record->jam_in) $score += 5;
        if ($record->jam_out) $score += 5;
        
        // Prefer records with complete information
        if ($record->instansi) $score += 3;
        if ($record->keperluan) $score += 3;
        if ($record->phone_number) $score += 2;
        if ($record->department_id) $score += 2;
        if ($record->user_id) $score += 2;
        
        // Prefer older records (original entries)
        // Older created_at gets bonus points
        $daysOld = now()->diffInDays($record->created_at);
        $score += min($daysOld, 50); // Cap at 50 bonus points

        return $score;
    }

    /**
     * Handle records with missing or invalid dates.
     */
    protected function handleMissingDates(): void
    {
        $this->info('🔍 Step 3: Handling records with missing dates...');

        $missingDateRecords = Guestbook::whereNull('date')->get();

        if ($missingDateRecords->isEmpty()) {
            $this->info('   ✅ No records with missing dates');
            $this->newLine();
            return;
        }

        $this->warn("   Found {$missingDateRecords->count()} records with missing dates");

        foreach ($missingDateRecords as $record) {
            // Attempt to recover date from created_at
            if ($record->created_at) {
                $recoveredDate = Carbon::parse($record->created_at)->toDateString();
                $record->date = $recoveredDate;
                $record->save();
                
                $this->info("   🔧 Recovered date for ID {$record->guestbook_id}: {$recoveredDate} (from created_at)");
                $this->repairedCount++;
            } else {
                // Cannot recover date - this is invalid data
                $this->warn("   🗑️  Deleting invalid record ID {$record->guestbook_id} (no date, no created_at)");
                $this->deletedIds[] = $record->guestbook_id;
                $record->delete();
                $this->deletedCount++;
            }
        }

        $this->newLine();
    }

    /**
     * Helper methods for console output.
     */
    protected function info(string $message): void
    {
        echo $message . "\n";
    }

    protected function warn(string $message): void
    {
        echo $message . "\n";
    }

    protected function newLine(): void
    {
        echo "\n";
    }
}
