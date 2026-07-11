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
        $updated = VehicleBooking::where('status', 'approved')
            ->where('start_at', '<=', DB::raw('NOW()'))
            ->where(function($query) {
                $query->whereNull('end_at')
                      ->orWhere('end_at', '>', DB::raw('NOW()'));
            })
            ->update(['status' => 'on_progress']);

        $this->info("Updated {$updated} vehicle bookings to on_progress.");
    }
}
