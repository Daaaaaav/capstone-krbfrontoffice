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
    protected $description = 'Auto-reject pending vehicle bookings if their end time has passed.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::transaction(function () {
            // Get all pending bookings where current time >= end_at
            $bookings = VehicleBooking::where('status', 'pending')
                ->where('end_at', '<=', Carbon::now())
                ->lockForUpdate()
                ->get();

            $count = 0;
            foreach ($bookings as $booking) {
                $booking->status = 'rejected';
                
                $note = 'cancel by system';
                if (empty($booking->notes)) {
                    $booking->notes = $note;
                } else {
                    $booking->notes .= "\n" . $note;
                }
                
                $booking->save();
                $count++;
            }

            $this->info("Auto-rejected {$count} vehicle bookings.");
        });
    }
}
