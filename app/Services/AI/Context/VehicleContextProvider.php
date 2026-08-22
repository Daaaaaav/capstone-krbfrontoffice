<?php

namespace App\Services\AI\Context;

use App\Models\Vehicle;
use App\Models\VehicleBooking;
use App\Services\AI\Contracts\ContextProviderInterface;
use App\Services\AI\Enums\ContextDetailLevel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class VehicleContextProvider implements ContextProviderInterface
{
    private string $tz = 'Asia/Jakarta';

    public function name(): string
    {
        return 'vehicles';
    }

    public function load(?int $companyId, array $params = [], ?ContextDetailLevel $detailLevel = null): string
    {
        if (! $companyId) {
            return '(no vehicle data available: company not specified)';
        }

        $now   = Carbon::now($this->tz);
        $today = $params['date'] ?? $now->toDateString();
        $level = $detailLevel ?? ContextDetailLevel::DETAILED;

        $cacheKey = "ctx_vehicles_{$companyId}_{$today}_{$level->value}";
        return Cache::remember($cacheKey, 90, fn() => $this->build($companyId, $now, $today, $level));
    }

    private function build(int $companyId, Carbon $now, string $today, ContextDetailLevel $level): string
    {
        return match ($level) {
            ContextDetailLevel::MINIMAL => $this->buildMinimal($companyId),
            ContextDetailLevel::NORMAL => $this->buildNormal($companyId),
            ContextDetailLevel::BOOKING => $this->buildBooking($companyId, $now, $today),
            ContextDetailLevel::DETAILED => $this->buildDetailed($companyId, $now, $today),
        };
    }

    private function buildMinimal(int $companyId): string
    {
        $vehicles = Vehicle::where('company_id', $companyId)
            ->where('is_active', 1)->orderBy('name')
            ->pluck('name')
            ->join(', ') ?: 'none';

        return "VEHICLES: {$vehicles}";
    }

    private function buildNormal(int $companyId): string
    {
        $vehicles = Vehicle::where('company_id', $companyId)
            ->where('is_active', 1)->orderBy('name')
            ->get(['name', 'category'])
            ->map(fn($v) => "{$v->name} ({$v->category})")
            ->join(', ') ?: 'none';

        return "VEHICLES: {$vehicles}";
    }

    private function buildBooking(int $companyId, Carbon $now, string $today): string
    {
        $fleet = Vehicle::where('company_id', $companyId)
            ->where('is_active', 1)->orderBy('name')
            ->get(['vehicle_id', 'name', 'plate_number', 'category'])
            ->map(fn($v) => sprintf(
                '[VehicleID:%d] %s | Plate:%s | Type:%s',
                $v->vehicle_id, $v->name ?? '—', $v->plate_number ?? '—', $v->category ?? '—'
            ))->join("\n  ") ?: '(none)';

        $todayTrips = VehicleBooking::where('company_id', $companyId)
            ->with(['vehicle'])
            ->whereDate('start_at', $today)->orderBy('start_at')->take(6)->get()
            ->map(fn($v) => sprintf(
                '[ID:%d] %s | %s(%s) | %s–%s | %s',
                $v->vehiclebooking_id, $v->borrower_name ?? '—',
                $v->vehicle?->name ?? '—', $v->vehicle?->plate_number ?? '—',
                optional($v->start_at)->format('H:i') ?? '—',
                optional($v->end_at)->format('H:i') ?? '—',
                ucfirst($v->status ?? '—')
            ))->join("\n  ") ?: '(none)';

        return <<<BLOCK
        AVAILABLE VEHICLES:
          {$fleet}

        TODAY'S TRIPS ({$today}):
          {$todayTrips}
        BLOCK;
    }

    private function buildDetailed(int $companyId, Carbon $now, string $today): string
    {
        $fleet = Vehicle::where('company_id', $companyId)
            ->where('is_active', 1)->orderBy('name')
            ->get(['vehicle_id', 'name', 'plate_number', 'category'])
            ->map(fn($v) => sprintf(
                '  [VehicleID:%d] %s | Plate:%s | Type:%s',
                $v->vehicle_id, $v->name ?? '—', $v->plate_number ?? '—', $v->category ?? '—'
            ))->join("\n") ?: '  (none)';

        $todayTrips = VehicleBooking::where('company_id', $companyId)
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

        $recent = VehicleBooking::where('company_id', $companyId)
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
