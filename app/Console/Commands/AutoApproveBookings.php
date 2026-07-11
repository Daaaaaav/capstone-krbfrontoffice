<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tz      = config('app.timezone', 'Asia/Jakarta');
        $now     = Carbon::now($tz);
        $nowStr  = $now->toDateTimeString();
        $isDry   = $this->option('dry-run');

        $this->info('[' . $now->toDateTimeString() . '] Running auto-approve check...');

        // ──────────────────────────────────────────────────────────────────
        // 1. ROOM BOOKINGS
        //    pending → approved  when CONCAT(date, ' ', start_time) <= NOW()
        //
        //    The existing system treats 'approved' as the "ongoing" state for
        //    room bookings (see RoomApproval and BookingsApproval pages).
        // ──────────────────────────────────────────────────────────────────
        $roomQuery = DB::table('booking_rooms')
            ->whereNull('deleted_at')
            ->where('status', 'pending')
            ->whereNotNull('date')
            ->whereNotNull('start_time')
            ->whereRaw("CONCAT(date, ' ', start_time) <= ?", [$nowStr]);

        $roomCount = $roomQuery->count();

        if ($roomCount > 0) {
            if ($isDry) {
                $this->line("  [DRY-RUN] Would approve {$roomCount} pending room booking(s).");
            } else {
                $affected = DB::table('booking_rooms')
                    ->whereNull('deleted_at')
                    ->where('status', 'pending')
                    ->whereNotNull('date')
                    ->whereNotNull('start_time')
                    ->whereRaw("CONCAT(date, ' ', start_time) <= ?", [$nowStr])
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
        // 2. VEHICLE BOOKINGS — late return detection
        //    approved → late_return  when end_at < NOW()
        //
        //    Vehicle approvals are always manual (the receptionist must hand
        //    over the key). Once approved, if the borrower has not returned
        //    the vehicle by end_at the booking is flagged as 'late_return'.
        //    This blocks any new booking for the same vehicle until the
        //    receptionist marks it returned.
        //
        //    Note: on_progress is NOT included here — on_progress bookings
        //    have already had the key handed over and photos uploaded; their
        //    overdue handling follows the same rule but they also trigger
        //    late_return to surface them clearly for the receptionist.
        // ──────────────────────────────────────────────────────────────────
        $lateQuery = DB::table('vehicle_bookings')
            ->whereNull('deleted_at')
            ->whereIn('status', ['approved', 'on_progress'])
            ->whereNotNull('end_at')
            ->whereRaw('DATE_ADD(end_at, INTERVAL 1 HOUR) < ?', [$nowStr]);

        $lateCount = $lateQuery->count();

        if ($lateCount > 0) {
            if ($isDry) {
                $this->line("  [DRY-RUN] Would flag {$lateCount} vehicle booking(s) as late_return.");
            } else {
                $affected = DB::table('vehicle_bookings')
                    ->whereNull('deleted_at')
                    ->whereIn('status', ['approved', 'on_progress'])
                    ->whereNotNull('end_at')
                    ->whereRaw('DATE_ADD(end_at, INTERVAL 1 HOUR) < ?', [$nowStr])
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
