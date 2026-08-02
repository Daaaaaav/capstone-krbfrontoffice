<?php

namespace App\Services\AI\Tools;

use App\Models\Vehicle;
use App\Models\VehicleBooking;
use App\Services\AI\Contracts\ToolInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class VehicleAvailabilityTool implements ToolInterface
{
    public function name(): string
    {
        return 'check_vehicle_availability';
    }

    public function description(): string
    {
        return 'Check which vehicles are available or booked for a given date and optional time range. '
             . 'Use when the user asks about vehicle availability, borrowing a car, or needs to know '
             . 'if a specific vehicle is free.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'date'         => ['type' => 'string', 'description' => 'Date to check, YYYY-MM-DD'],
                'start_time'   => ['type' => 'string', 'description' => 'Optional start time HH:MM'],
                'end_time'     => ['type' => 'string', 'description' => 'Optional end time HH:MM'],
                'vehicle_name' => ['type' => 'string', 'description' => 'Optional: check a specific vehicle by name'],
            ],
            'required' => ['date'],
        ];
    }

    public function execute(array $arguments): array
    {
        $companyId   = Auth::user()?->company_id;
        $date        = $arguments['date']         ?? Carbon::today('Asia/Jakarta')->toDateString();
        $startTime   = $arguments['start_time']   ?? null;
        $endTime     = $arguments['end_time']     ?? null;
        $vehicleName = $arguments['vehicle_name'] ?? null;

        $vehicleQ = Vehicle::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->where('is_active', 1)
            ->orderBy('name');
        if ($vehicleName) {
            $vehicleQ->where('name', 'like', '%' . $vehicleName . '%');
        }
        $vehicles = $vehicleQ->get(['vehicle_id', 'name', 'plate_number', 'category']);
        $dayStart = $date . ' 00:00:00';
        $dayEnd   = $date . ' 23:59:59';

        $bookingQ = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereIn('status', ['pending', 'approved', 'on_progress'])
            ->where('start_at', '<=', $dayEnd)
            ->where('end_at',   '>=', $dayStart);

        if ($startTime && $endTime) {
            $startDt = $date . ' ' . $startTime;
            $endDt   = $date . ' ' . $endTime;
            $bookingQ->where('start_at', '<', $endDt)
                     ->whereRaw('DATE_ADD(end_at, INTERVAL 1 HOUR) > ?', [$startDt]);
        }

        $bookings    = $bookingQ->get();
        $occupiedIds = $bookings->pluck('vehicle_id')->unique()->toArray();

        $lines = ["Vehicle availability on {$date}" . ($startTime ? " ({$startTime}–{$endTime})" : '') . ':'];
        $free = $busy = 0;

        foreach ($vehicles as $v) {
            $occupied = in_array($v->vehicle_id, $occupiedIds);
            if ($occupied) {
                $busy++;
                $vBookings = $bookings->where('vehicle_id', $v->vehicle_id);
                $slots = $vBookings->map(fn($b) =>
                    optional($b->start_at)->format('H:i') . '–' . optional($b->end_at)->format('H:i')
                    . ' (' . ($b->borrower_name ?? '—') . ')'
                )->implode(', ');
                $lines[] = "  [VehicleID:{$v->vehicle_id}] {$v->name} ({$v->plate_number}) — OCCUPIED: {$slots}";
            } else {
                $free++;
                $lines[] = "  [VehicleID:{$v->vehicle_id}] {$v->name} ({$v->plate_number}) — FREE";
            }
        }

        $lines[] = "Summary: {$free} free, {$busy} occupied.";

        return ['text' => implode("\n", $lines)];
    }
}
