<?php
/**
 * fix_late_return.php
 *
 * Patches the LIVE database so that the first two vehicles (by vehicle_id) per company
 * have NO late_return bookings — those are converted to 'returned'.
 * The other two vehicles keep their late_return status unchanged.
 *
 * Usage: php fix_late_return.php
 * Run this once from the project root while Laragon (MySQL) is running.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$companies = DB::table('vehicles')
    ->selectRaw('DISTINCT company_id')
    ->pluck('company_id');

foreach ($companies as $companyId) {
    $vehicleIds = DB::table('vehicles')
        ->where('company_id', $companyId)
        ->orderBy('vehicle_id')
        ->pluck('vehicle_id');

    // First two vehicles per company → clear any late_return
    $cleanIds = $vehicleIds->take(2)->values();

    if ($cleanIds->isEmpty()) {
        continue;
    }

    $affected = DB::table('vehicle_bookings')
        ->where('status', 'late_return')
        ->whereIn('vehicle_id', $cleanIds->toArray())
        ->update([
            'status'     => 'returned',
            'updated_at' => now(),
        ]);

    echo "Company {$companyId}: converted {$affected} late_return → returned"
        . " for vehicle IDs [" . $cleanIds->implode(', ') . "]\n";
}

echo "\nDone. The other vehicles retain their late_return status.\n";
