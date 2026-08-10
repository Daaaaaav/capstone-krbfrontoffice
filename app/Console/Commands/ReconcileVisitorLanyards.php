<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VisitorLanyard;
use App\Models\Guestbook;

class ReconcileVisitorLanyards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lanyards:reconcile 
                            {--dry-run : Show what would be fixed without making changes}
                            {--company= : Only reconcile lanyards for a specific company ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile visitor lanyard availability - fix lanyards marked unavailable but not assigned to active visitors';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $companyId = $this->option('company');

        $this->info('Starting visitor lanyard reconciliation...');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get all unavailable lanyards
        $query = VisitorLanyard::where('status', 0);
        
        if ($companyId) {
            $query->where('company_id', $companyId);
            $this->info("Filtering by company ID: {$companyId}");
        }

        $unavailableLanyards = $query->get();

        if ($unavailableLanyards->isEmpty()) {
            $this->info('✓ No unavailable lanyards found. All lanyards are properly marked as available.');
            return Command::SUCCESS;
        }

        $this->info("Found {$unavailableLanyards->count()} unavailable lanyard(s)");
        $this->newLine();

        $fixedCount = 0;
        $skippedCount = 0;

        foreach ($unavailableLanyards as $lanyard) {
            // Check if this lanyard is actually assigned to an active visitor
            $activeAssignment = Guestbook::where('visitor_lanyard_id', $lanyard->id)
                ->whereNull('jam_out')
                ->whereNull('deleted_at')
                ->first();

            if ($activeAssignment) {
                // Lanyard is correctly marked as unavailable - skip
                $this->line("  [SKIP] Lanyard '{$lanyard->lanyard_name}' (ID: {$lanyard->id}) - Currently assigned to active visitor: {$activeAssignment->name}");
                $skippedCount++;
            } else {
                // Lanyard should be available but is marked unavailable - fix it
                $historicalCount = Guestbook::where('visitor_lanyard_id', $lanyard->id)->count();
                
                $this->warn("  [FIX]  Lanyard '{$lanyard->lanyard_name}' (ID: {$lanyard->id}) - Not assigned to active visitor (historical uses: {$historicalCount})");
                
                if (!$dryRun) {
                    $lanyard->update(['status' => 1]);
                    $this->info("         → Status updated to available");
                }
                
                $fixedCount++;
            }
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info("Summary:");
        $this->info("  Total unavailable lanyards checked: {$unavailableLanyards->count()}");
        $this->info("  Correctly unavailable (active): {$skippedCount}");
        
        if ($dryRun) {
            $this->warn("  Would fix (incorrectly unavailable): {$fixedCount}");
        } else {
            $this->info("  Fixed (now available): {$fixedCount}");
        }
        
        $this->info('═══════════════════════════════════════════════════════');

        if ($dryRun && $fixedCount > 0) {
            $this->newLine();
            $this->comment("Run without --dry-run to apply these changes:");
            $this->comment("  php artisan lanyards:reconcile");
        }

        return Command::SUCCESS;
    }
}
