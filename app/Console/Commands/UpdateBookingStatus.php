<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\VehicleBooking;
use Carbon\Carbon;

class UpdateBookingStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automate vehicle booking status transitions based on start time.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now('Asia/Jakarta');


        // 2. Approved State -> On Progress
        // If now() >= start_at and status is approved, automatically move to on_progress.
        // Do NOT check the end_at time for this transition.
        $affectedOnProgress = VehicleBooking::where('status', 'approved')
            ->where('start_at', '<=', $now)
            ->update([
                'status' => 'on_progress'
            ]);

        $this->info("Booking status updated. On Progress: {$affectedOnProgress}");
    }
}
