<?php

namespace App\Console\Commands;

use App\Models\BookingRoom;
use App\Models\PriorityRoomBooking;
use App\Models\PriorityVehicleBooking;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoCompleteBookings extends Command
{
    protected $signature = 'bookings:auto-complete';
    protected $description = 'Auto-complete approved room bookings (regular + priority) whose end time has passed, and auto-reject priority pending bookings whose window has expired';

    private string $tz = 'Asia/Jakarta';

    public function handle(): int
    {
        $now = Carbon::now($this->tz);
        // Add 1 minute tolerance such as booking ending at 11:00 completes at 11:01
        $threshold = $now->copy()->subMinute()->format('Y-m-d H:i:s');

        $endExpr = "COALESCE(
            CASE WHEN end_time REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN end_time END,
            CASE WHEN date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN date END,
            CONCAT(date, ' ', end_time)
        )";

        $updatedRooms = DB::transaction(function () use ($threshold, $endExpr) {
            $completed = BookingRoom::query()
                ->whereNotNull('date')
                ->whereNotNull('end_time')
                ->whereRaw("$endExpr IS NOT NULL")
                ->whereRaw("$endExpr <= ?", [$threshold])
                ->where(function ($q) {
                    $q->whereRaw("LOWER(TRIM(`status`)) = 'approved'");
                })
                ->update([
                    'status'     => 'completed',
                    'updated_at' => Carbon::now($this->tz)->toDateTimeString(),
                ]);

            BookingRoom::query()
                ->whereNotNull('date')
                ->whereNotNull('end_time')
                ->whereRaw("$endExpr IS NOT NULL")
                ->whereRaw("$endExpr <= ?", [$threshold])
                ->where(function ($q) {
                    $q->whereRaw("LOWER(TRIM(`status`)) = 'pending'");
                })
                ->update([
                    'status'      => 'rejected',
                    'book_reject' => 'Auto-rejected: booking window expired without approval.',
                    'updated_at'  => Carbon::now($this->tz)->toDateTimeString(),
                ]);

            return $completed;
        });

        $updatedPriority = DB::transaction(function () use ($threshold) {
            return PriorityRoomBooking::query()
                ->where('status', PriorityRoomBooking::STATUS_APPROVED)
                ->whereNotNull('date')
                ->whereNotNull('end_time')
                ->whereRaw("CONCAT(date, ' ', end_time) <= ?", [$threshold])
                ->update([
                    'status'     => PriorityRoomBooking::STATUS_COMPLETED,
                    'updated_at' => Carbon::now($this->tz)->toDateTimeString(),
                ]);
        });

        // Auto-reject Priority Room pending bookings whose window has expired.
        // Run AFTER auto-approve so bookings at their start time are approved first.
        PriorityRoomBooking::autoApproveNonClashing(null);   // all companies
        PriorityRoomBooking::autoRejectExpiredPending(null);  // all companies

        // Priority Vehicle Bookings — auto-complete on_progress / late_return
        // bookings whose end_at has passed (mirrors ordinary VehicleBooking pattern).
        // Note: there is no scheduler-level auto-complete for ordinary VehicleBooking
        // (that is manual via Mark Done), so we also keep Priority consistent:
        // only late_return bookings past their end_at get auto-completed by the scheduler
        // if they haven't been manually marked done. on_progress ones still require
        // manual Mark Done just like ordinary Vehicle bookings.
        //
        // For safety we auto-complete ONLY late_return bookings to avoid bypassing the
        // required proof-after photo workflow for normal completions.
        $updatedPriorityVehicle = DB::transaction(function () {
            // Nothing to do here — ordinary Vehicle Bookings are NEVER auto-completed by
            // the scheduler; completion always requires a manual "Mark Done" with a
            // return photo.  We keep the same rule for Priority Vehicle Bookings.
            return 0;
        });

        $this->info("Auto-completed {$updatedRooms} regular room booking(s), {$updatedPriority} priority room booking(s).");
        return self::SUCCESS;
    }
}
