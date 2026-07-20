<?php

namespace App\Services\AI\Context;

use App\Models\Vehicle;
use App\Models\VehicleBooking;
use App\Services\AI\Contracts\ContextProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Loads vehicle-related context: fleet list, today's trips, recent bookings.
 * Called only when the ContextRouter detects vehicle-booking intent.
 */
class VehicleContextProvider implements ContextProviderInterface
{
    private string $tz = 'Asia/Jakarta';

    public function name(): string
    {
        return 'vehicles';
    }

    public function load(?int $companyId, array $params = []): string
    {
        $now   = Carbon::now($this->tz);
        $today = $params['date'] ?? $now->toDateString();

        $cacheKey = "ctx_vehicles_{$companyId}_{$today}";
        return Cache::remember($cacheKey, 90, fn() => $this->build($companyId, $now, $today));
    }

    private function build(?int $companyId, Carbon $now, string $today): string
    {
        // Active fleet
        $fleet = Vehicle::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->where('is_active', 1)->orderBy('name')
            ->get(['vehicle_id', 'name', 'plate_number', 'category'])
            ->map(fn($v) => sprintf(
                '  [VehicleID:%d] %s | Plate:%s | Type:%s',
                $v->vehicle_id, $v->name ?? '—', $v->plate_number ?? '—', $v->category ?? '—'
            ))->join("\n") ?: '  (none)';

        // Today's trips (cap 6)
        $todayTrips = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['vehicle', 'department'])
            ->whereDate('start_at', $today)->orderBy('start_at')->take(6)->get()
            ->map(fn($v) => sprintf(
                '  [ID:%d] %s | %s(%s) | %s–%s | Dest:%s | %s | %s',
                $v->vehiclebooking_id, $v->borrower_name ?? '—',
                $v->vehicle?->name ?? '—', $v->vehicle?->plate_number ?? '—',
                optional($v->start_at)->format('H:i') ?? '—',
                optional($v->end_at)->format('H:i') ?? '—',
                $v->destination ?? '—', $v->department?->name ?? '—',
                ucfirst($v->status ?? '—')
            ))->join("\n") ?: '  (none)';

        // Recent trips last 60 days (cap 8)
        $recent = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['vehicle', 'department'])
            ->where('start_at', '>=', $now->copy()->subDays(59)->startOfDay())
            ->orderByDesc('start_at')->take(8)->get()
            ->map(fn($v) => sprintf(
                '  [ID:%d] %s | %s(%s) | %s→%s | Purpose:%s | Dept:%s | %s',
                $v->vehiclebooking_id, $v->borrower_name ?? '—',
                $v->vehicle?->name ?? '—', $v->vehicle?->plate_number ?? '—',
                optional($v->start_at)->format('d M H:i') ?? '—',
                optional($v->end_at)->format('d M H:i') ?? '—',
                $v->purpose ?? '—', $v->department?->name ?? '—',
                ucfirst($v->status ?? '—')
            ))->join("\n") ?: '  (none)';

        return <<<BLOCK
        AVAILABLE VEHICLES:
        {$fleet}

        TODAY'S VEHICLE TRIPS ({$today}):
        {$todayTrips}

        RECENT VEHICLE BOOKINGS (last 60 days, ≤8):
        {$recent}
        BLOCK;
    }
}
