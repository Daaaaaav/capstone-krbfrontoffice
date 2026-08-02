<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\VehicleBooking;
use Carbon\Carbon;

class UpdateBookingStatus extends Command
{
    protected $signature = 'booking:update-status';

    protected $description = 'Automate vehicle booking status transitions based on start time.';

    public function handle()
    {
        $now = Carbon::now('Asia/Jakarta');
        
        $affectedOnProgress = VehicleBooking::where('status', 'approved')
            ->where('start_at', '<=', $now)
            ->update([
                'status' => 'on_progress'
            ]);

        $this->info("Booking status updated. On Progress: {$affectedOnProgress}");
    }
}
