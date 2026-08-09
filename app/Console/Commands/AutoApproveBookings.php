<?php

namespace App\Console\Commands;

use App\Models\BookingRoom;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoApproveBookings extends Command
{
    protected $signature = 'bookings:auto-approve
                            {--dry-run : Preview what would be updated without making changes}';

    protected $description = 'Auto-approve pending room and vehicle bookings whose start time has arrived';

    private string $tz = 'Asia/Jakarta';

    public function handle(): int
    {
        $tz      = config('app.timezone', $this->tz);
        $now     = Carbon::now($tz);
        $nowStr  = $now->toDateTimeString();
        $isDry   = $this->option('dry-run');

        $this->info('[' . $now->toDateTimeString() . '] Running auto-approve check...');

        $startExpr = "COALESCE(
            CASE WHEN start_time REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN start_time END,
            CASE WHEN date       REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN date       END,
            CONCAT(date, ' ', start_time)
        )";

        $endExpr = "COALESCE(
            CASE WHEN end_time REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN end_time END,
            CASE WHEN date     REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN date     END,
            CONCAT(date, ' ', end_time)
        )";

        $roomQuery = BookingRoom::query()
            ->where('status', 'pending')
            ->whereNotNull('date')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->whereRaw("$startExpr IS NOT NULL")
            ->whereRaw("$endExpr IS NOT NULL")
            ->whereRaw("$startExpr <= ?", [$nowStr])   // start time arrived
            ->whereRaw("$endExpr > ?",   [$nowStr]);    // end time not passed yet

        $roomCount = $roomQuery->count();

        if ($roomCount > 0) {
            if ($isDry) {
                $this->line("  [DRY-RUN] Would auto-approve {$roomCount} room booking(s) whose start time has arrived.");
            } else {
                $affected = BookingRoom::query()
                    ->where('status', 'pending')
                    ->whereNotNull('date')
                    ->whereNotNull('start_time')
                    ->whereNotNull('end_time')
                    ->whereRaw("$startExpr IS NOT NULL")
                    ->whereRaw("$endExpr IS NOT NULL")
                    ->whereRaw("$startExpr <= ?", [$nowStr])
                    ->whereRaw("$endExpr > ?",   [$nowStr])
                    ->update([
                        'status'     => 'approved',
                        'updated_at' => $nowStr,
                    ]);

                $this->info("  ✓ Room bookings auto-approved (start time reached): {$affected}");
            }
        } else {
            $this->line('  Room bookings: none to auto-approve.');
        }

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
