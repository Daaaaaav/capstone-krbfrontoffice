<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VehicleBooking;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AutoRejectVehicleBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:auto-reject';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-reject pending vehicle bookings whose scheduled start datetime has already passed.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::transaction(function () {
            // Reject any pending booking whose start time has already passed
            // (the booking was never approved before it was supposed to begin).
            $bookings = VehicleBooking::where('status', 'pending')
                ->where('start_at', '<=', Carbon::now())
                ->lockForUpdate()
                ->get();

            $count = 0;
            foreach ($bookings as $booking) {
                $booking->status = 'rejected';

                $note = '[Rejected] Auto-rejected by system: booking start time passed without approval.';
                if (empty($booking->notes)) {
                    $booking->notes = $note;
                } else {
                    $booking->notes .= "\n" . $note;
                }

                $booking->save();
                $count++;
            }

            $this->info("Auto-rejected {$count} pending vehicle booking(s) whose start time has passed.");
        });
    }
}
