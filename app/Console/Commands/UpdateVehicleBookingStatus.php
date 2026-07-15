<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VehicleBooking;
use Illuminate\Support\Facades\DB;

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
    protected $description = 'Automatically update approved vehicle bookings to on_progress when their start time arrives.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bookings = VehicleBooking::where('status', 'approved')
            ->where('start_at', '<=', \Carbon\Carbon::now())
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $booking->status = 'on_progress';
            $booking->save();
            $count++;
        }

        $this->info("Updated {$count} vehicle bookings to on_progress.");
    }
}
