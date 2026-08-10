<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Guestbook;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DiagnoseGuestbookDuplicates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'guestbook:diagnose-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose duplicate and N/A entries in Guestbook records (READ-ONLY)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('╔═══════════════════════════════════════════════════════════════╗');
        $this->info('║       GUESTBOOK DUPLICATE DIAGNOSTIC (READ-ONLY)              ║');
        $this->info('╚═══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // STEP 1: TOTAL COUNT
        $totalGuestbooks = Guestbook::count();
        $this->info("📊 Total Guestbook records: {$totalGuestbooks}");
        $this->newLine();

        // STEP 2: RECORDS WITH MISSING DATE
        $missingDate = Guestbook::whereNull('date')->get();
        $this->warn("⚠️  Records with missing/null 'date': {$missingDate->count()}");
        if ($missingDate->isNotEmpty()) {
            $this->table(
                ['ID', 'Name', 'Date', 'Check In', 'Check Out', 'ID Type', 'Lanyard', 'Created At'],
                $missingDate->map(fn($g) => [
                    $g->guestbook_id,
                    $g->name,
                    $g->date ?? 'NULL',
                    $g->jam_in ?? '-',
                    $g->jam_out ?? '-',
                    $g->id_type_id ?? 'NULL',
                    $g->visitor_lanyard_id ?? 'NULL',
                    $g->created_at,
                ])
            );
        }
        $this->newLine();

        // STEP 3: RECORDS WITH MISSING ID_TYPE_ID
        $missingIdType = Guestbook::whereNull('id_type_id')->get();
        $this->warn("⚠️  Records with missing/null 'id_type_id' (will show N/A in UI): {$missingIdType->count()}");
        if ($missingIdType->isNotEmpty()) {
            $this->table(
                ['ID', 'Name', 'Date', 'Check In', 'Institution', 'Purpose', 'ID Type', 'Lanyard', 'Created At'],
                $missingIdType->map(fn($g) => [
                    $g->guestbook_id,
                    $g->name,
                    $g->date ?? 'NULL',
                    $g->jam_in ?? '-',
                    $g->instansi ?? '-',
                    substr($g->keperluan ?? '-', 0, 30),
                    $g->id_type_id ?? 'NULL',
                    $g->visitor_lanyard_id ?? 'NULL',
                    $g->created_at,
                ])
            );
        }
        $this->newLine();

        // STEP 4: RECORDS WITH MISSING VISITOR_LANYARD_ID
        $missingLanyard = Guestbook::whereNull('visitor_lanyard_id')->get();
        $this->warn("⚠️  Records with missing/null 'visitor_lanyard_id': {$missingLanyard->count()}");
        if ($missingLanyard->isNotEmpty()) {
            $this->table(
                ['ID', 'Name', 'Date', 'Check In', 'ID Type', 'Lanyard', 'Created At'],
                $missingLanyard->map(fn($g) => [
                    $g->guestbook_id,
                    $g->name,
                    $g->date ?? 'NULL',
                    $g->jam_in ?? '-',
                    $g->id_type_id ?? 'NULL',
                    $g->visitor_lanyard_id ?? 'NULL',
                    $g->created_at,
                ])
            );
        }
        $this->newLine();

        // STEP 5: SAME-DAY DUPLICATE ANALYSIS
        // Group by company_id, date, name and find duplicates
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

        $this->warn("⚠️  Same-day duplicate visitor groups: {$duplicateGroups->count()}");
        
        if ($duplicateGroups->isNotEmpty()) {
            foreach ($duplicateGroups as $group) {
                $this->warn("\n🔍 Duplicate Group:");
                $this->line("   Date: {$group->date}");
                $this->line("   Name: {$group->name}");
                $this->line("   Company ID: {$group->company_id}");
                $this->line("   Count: {$group->count}");
                
                $ids = explode(',', $group->ids);
                $records = Guestbook::whereIn('guestbook_id', $ids)->get();
                
                $this->table(
                    ['ID', 'Date', 'Check In', 'Check Out', 'Institution', 'Purpose', 'ID Type', 'Lanyard', 'Created At'],
                    $records->map(fn($g) => [
                        $g->guestbook_id,
                        $g->date,
                        $g->jam_in ?? '-',
                        $g->jam_out ?? '-',
                        $g->instansi ?? '-',
                        substr($g->keperluan ?? '-', 0, 25),
                        $g->id_type_id ?? 'NULL',
                        $g->visitor_lanyard_id ?? 'NULL',
                        $g->created_at,
                    ])
                );
            }
        }
        $this->newLine();

        // STEP 6: INVALID DATE VALUES
        $invalidDates = Guestbook::whereNotNull('date')
            ->get()
            ->filter(function ($g) {
                try {
                    Carbon::parse($g->date);
                    return false;
                } catch (\Exception $e) {
                    return true;
                }
            });

        $this->warn("⚠️  Records with invalid date values: {$invalidDates->count()}");
        if ($invalidDates->isNotEmpty()) {
            $this->table(
                ['ID', 'Name', 'Date (raw)', 'Created At'],
                $invalidDates->map(fn($g) => [
                    $g->guestbook_id,
                    $g->name,
                    $g->date,
                    $g->created_at,
                ])
            );
        }
        $this->newLine();

        // STEP 7: STATISTICS QUERY SIMULATION
        // Simulate what the GuestbookStatistics component sees
        $this->info('📈 Simulating Manager Dashboard "Recent Visitors" query...');
        
        $testStartDate = now()->startOfMonth();
        $testEndDate = now();
        
        $recentVisitors = Guestbook::whereBetween('created_at', [$testStartDate, $testEndDate])
            ->with('idType')
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        $this->info("   Found {$recentVisitors->count()} recent visitors in current month");
        
        $recordsWithNullIdType = $recentVisitors->filter(fn($g) => is_null($g->id_type_id))->count();
        $this->warn("   Records that would display 'N/A' for ID Type: {$recordsWithNullIdType}");
        
        if ($recordsWithNullIdType > 0) {
            $this->newLine();
            $this->table(
                ['ID', 'Name', 'Date', 'ID Type ID', 'ID Type Name', 'Created At'],
                $recentVisitors->filter(fn($g) => is_null($g->id_type_id))->map(fn($g) => [
                    $g->guestbook_id,
                    $g->name,
                    $g->date ? $g->date->format('d/m/Y') : '-',
                    $g->id_type_id ?? 'NULL',
                    $g->idType->id_type_name ?? 'N/A',
                    $g->created_at,
                ])
            );
        }
        $this->newLine();

        // STEP 8: SUMMARY
        $this->info('╔═══════════════════════════════════════════════════════════════╗');
        $this->info('║                      DIAGNOSTIC SUMMARY                       ║');
        $this->info('╚═══════════════════════════════════════════════════════════════╝');
        $this->line("Total Guestbook records: {$totalGuestbooks}");
        $this->line("Records with missing date: {$missingDate->count()}");
        $this->line("Records with missing id_type_id (N/A in UI): {$missingIdType->count()}");
        $this->line("Records with missing visitor_lanyard_id: {$missingLanyard->count()}");
        $this->line("Same-day duplicate name groups: {$duplicateGroups->count()}");
        $this->line("Records with invalid date values: {$invalidDates->count()}");
        $this->newLine();

        $totalProblems = $missingDate->count() + $missingIdType->count() + $missingLanyard->count() 
                        + ($duplicateGroups->sum('count') - $duplicateGroups->count()) + $invalidDates->count();

        if ($totalProblems === 0) {
            $this->info('✅ No data quality issues detected!');
        } else {
            $this->warn("⚠️  Total problematic records: {$totalProblems}");
            $this->newLine();
            $this->info('💡 Next steps:');
            $this->line('   1. Review the detailed output above');
            $this->line('   2. Determine which records are genuine duplicates vs. legitimate data');
            $this->line('   3. Run the cleanup command only after confirming which records to remove');
        }

        $this->newLine();
        return Command::SUCCESS;
    }
}
