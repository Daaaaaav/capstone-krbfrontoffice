<?php

namespace App\Console\Commands;

use App\Models\BookingRoom;
use App\Models\PriorityRoomBooking;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\VehicleBooking;

class UpdateVehicleBookingStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:auto-start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-start approved bookings (vehicle + room + priority room) when their start time arrives.';

    private string $tz = 'Asia/Jakarta';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now($this->tz);
        $nowStr = $now->toDateTimeString();

        // ── 1. VEHICLE BOOKINGS — approved → on_progress ─────────────────────
        // When start_at <= now() and status is still approved, move to on_progress.
        $vehicleCount = 0;
        $vehicleBookings = VehicleBooking::where('status', 'approved')
            ->where('start_at', '<=', $now)
            ->get();

        foreach ($vehicleBookings as $booking) {
            $booking->status = 'on_progress';
            $booking->save();
            $vehicleCount++;
        }

        // ── 2. ROOM BOOKINGS — approved → ongoing ────────────────────────────
        // The booking_rooms table stores status as an ENUM('pending','approved',
        // 'rejected','completed'). There is intentionally no 'ongoing' ENUM value.
        // "Ongoing" is determined by a time-window query on approved rows:
        //   start_time <= now()  AND  end_time > now()
        // This is handled by the RoomApproval Livewire component's Ongoing tab,
        // and approved rows whose end_time has passed are completed by the
        // bookings:auto-complete command. No DB status change is required here.
        //
        // Priority room bookings follow the same pattern — approved rows are
        // shown as ongoing based on time, and completed by bookings:auto-complete.
        $roomOngoingCount = BookingRoom::query()
            ->whereNotNull('date')
            ->whereNotNull('start_time')
            ->whereRaw("LOWER(TRIM(`status`)) = 'approved'")
            ->whereRaw("COALESCE(
                CASE WHEN start_time REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN start_time END,
                CASE WHEN date       REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN date END,
                CONCAT(date, ' ', start_time)
            ) <= ?", [$nowStr])
            ->count();

        $priorityOngoingCount = PriorityRoomBooking::where('status', PriorityRoomBooking::STATUS_APPROVED)
            ->whereNotNull('date')
            ->whereNotNull('start_time')
            ->whereRaw("CONCAT(date, ' ', start_time) <= ?", [$nowStr])
            ->count();

        $this->info("Vehicle bookings started (on_progress): {$vehicleCount}");
        $this->info("Room bookings currently ongoing (time-window, no DB change): {$roomOngoingCount}");
        $this->info("Priority room bookings currently ongoing (time-window, no DB change): {$priorityOngoingCount}");

        return self::SUCCESS;
    }
}
