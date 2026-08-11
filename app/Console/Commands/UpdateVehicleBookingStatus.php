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
    protected $signature = 'bookings:auto-start';

    protected $description = 'Auto-start approved bookings (vehicle + room + priority room) when their start time arrives.';

    private string $tz = 'Asia/Jakarta';

    public function handle(): int
    {
        $now = Carbon::now($this->tz);
        $nowStr = $now->toDateTimeString();

        $vehicleCount = 0;
        $vehicleBookings = VehicleBooking::where('status', 'approved')
            ->where('start_at', '<=', $now)
            ->get();

        foreach ($vehicleBookings as $booking) {
            $booking->status = 'on_progress';
            $booking->save();
            $vehicleCount++;
        }

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

        \App\Models\PriorityVehicleBooking::autoProgressToOnProgress(null);

        $this->info("Vehicle bookings started (on_progress): {$vehicleCount}");
        $this->info("Room bookings currently ongoing (time-window, no DB change): {$roomOngoingCount}");
        $this->info("Priority room bookings currently ongoing (time-window, no DB change): {$priorityOngoingCount}");

        return self::SUCCESS;
    }
}
