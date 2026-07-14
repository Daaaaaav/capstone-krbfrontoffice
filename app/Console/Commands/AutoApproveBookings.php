<?php

namespace App\Console\Commands;

use App\Models\BookingRoom;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoApproveBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:auto-approve
                            {--dry-run : Preview what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-approve pending room and vehicle bookings whose start time has arrived';

    private string $tz = 'Asia/Jakarta';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tz      = config('app.timezone', $this->tz);
        $now     = Carbon::now($tz);
        $nowStr  = $now->toDateTimeString();
        $isDry   = $this->option('dry-run');

        $this->info('[' . $now->toDateTimeString() . '] Running auto-approve check...');

        // ──────────────────────────────────────────────────────────────────
        // 1. ALL ROOM BOOKINGS (offline + online)
        //    pending → approved when start_time <= NOW()
        //    Link creation for online meetings happens at manual approval or
        //    can be handled by a separate link-creation job if needed.
        // ──────────────────────────────────────────────────────────────────
        $pendingQuery = DB::table('booking_rooms')
            ->whereNull('deleted_at')
            ->where('status', 'pending')
            ->whereNotNull('date')
            ->whereNotNull('start_time')
            ->whereRaw("COALESCE(
                CASE WHEN start_time REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN start_time END,
                CASE WHEN date       REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN date END,
                CONCAT(date, ' ', start_time)
            ) <= ?", [$nowStr]);

        $pendingCount = $pendingQuery->count();

        if ($pendingCount > 0) {
            if ($isDry) {
                $this->line("  [DRY-RUN] Would approve {$pendingCount} pending room booking(s).");
            } else {
                $affected = DB::table('booking_rooms')
                    ->whereNull('deleted_at')
                    ->where('status', 'pending')
                    ->whereNotNull('date')
                    ->whereNotNull('start_time')
                    ->whereRaw("COALESCE(
                        CASE WHEN start_time REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN start_time END,
                        CASE WHEN date       REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN date END,
                        CONCAT(date, ' ', start_time)
                    ) <= ?", [$nowStr])
                    ->update([
                        'status'     => 'approved',
                        'is_approve' => 1,
                        'updated_at' => $nowStr,
                    ]);

                $this->info("  ✓ Room bookings auto-approved: {$affected}");
            }
        } else {
            $this->line('  Room bookings: none to auto-approve.');
        }

        // ──────────────────────────────────────────────────────────────────
        // 2. VEHICLE BOOKINGS — approved → on_progress
        //    When an approved vehicle booking's start_at <= NOW()
        //    (and end_at has NOT yet passed), mark it as on_progress.
        // ──────────────────────────────────────────────────────────────────
        $progressQuery = DB::table('vehicle_bookings')
            ->whereNull('deleted_at')
            ->where('status', 'approved')
            ->whereNotNull('start_at')
            ->whereNotNull('end_at')
            ->where('start_at', '<=', $nowStr)
            ->where('end_at', '>=', $nowStr);

        $progressCount = $progressQuery->count();

        if ($progressCount > 0) {
            if ($isDry) {
                $this->line("  [DRY-RUN] Would mark {$progressCount} vehicle booking(s) as on_progress.");
            } else {
                $affected = DB::table('vehicle_bookings')
                    ->whereNull('deleted_at')
                    ->where('status', 'approved')
                    ->whereNotNull('start_at')
                    ->whereNotNull('end_at')
                    ->where('start_at', '<=', $nowStr)
                    ->where('end_at', '>=', $nowStr)
                    ->update([
                        'status'     => 'on_progress',
                        'updated_at' => $nowStr,
                    ]);

                $this->info("  ✓ Vehicle bookings marked as on_progress: {$affected}");
            }
        } else {
            $this->line('  Vehicle bookings: none to mark as on_progress.');
        }

        // ──────────────────────────────────────────────────────────────────
        // 3. VEHICLE BOOKINGS — late return detection
        //    approved/on_progress → late_return  when end_at < NOW()
        // ──────────────────────────────────────────────────────────────────
        $lateQuery = DB::table('vehicle_bookings')
            ->whereNull('deleted_at')
            ->whereIn('status', ['approved', 'on_progress'])
            ->whereNotNull('end_at')
            ->where('end_at', '<', $nowStr);

        $lateCount = $lateQuery->count();

        if ($lateCount > 0) {
            if ($isDry) {
                $this->line("  [DRY-RUN] Would flag {$lateCount} vehicle booking(s) as late_return.");
            } else {
                $affected = DB::table('vehicle_bookings')
                    ->whereNull('deleted_at')
                    ->whereIn('status', ['approved', 'on_progress'])
                    ->whereNotNull('end_at')
                    ->where('end_at', '<', $nowStr)
                    ->update([
                        'status'     => 'late_return',
                        'updated_at' => $nowStr,
                    ]);

                $this->info("  ✓ Vehicle bookings flagged as late_return: {$affected}");
            }
        } else {
            $this->line('  Vehicle bookings: none to flag as late_return.');
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
