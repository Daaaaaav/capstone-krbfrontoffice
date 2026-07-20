<?php

namespace App\Services\AI\Tools;

use App\Models\BookingRoom;
use App\Models\Room;
use App\Services\AI\Contracts\ToolInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Tool: get_occupancy_stats
 *
 * Returns room occupancy rates for a given period.
 * Reads BookingRoom and Room data only. Results are cached for 5 minutes.
 */
class OccupancyTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_occupancy_stats';
    }

    public function description(): string
    {
        return 'Calculate room occupancy rates and utilisation statistics for a given period. '
             . 'Use when the user asks about occupancy rate, utilisation, how busy the rooms are, '
             . 'or which rooms are underused.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'period' => [
                    'type'   => 'string',
                    'enum'   => ['today', 'this_week', 'this_month', 'this_year'],
                    'description' => 'The time period to analyse.',
                ],
            ],
            'required' => ['period'],
        ];
    }

    public function execute(array $arguments): array
    {
        $companyId = Auth::user()?->company_id;
        $period    = $arguments['period'] ?? 'this_week';
        $cacheKey  = "ai_occupancy_{$companyId}_{$period}";

        $text = Cache::remember($cacheKey, 300, function () use ($companyId, $period) {
            return $this->buildOccupancyText($companyId, $period);
        });

        return ['text' => $text];
    }

    private function buildOccupancyText(?int $companyId, string $period): string
    {
        $tz  = 'Asia/Jakarta';
        $now = Carbon::now($tz);

        [$start, $end, $totalSlots] = $this->periodMeta($now, $period);

        $rooms = Room::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->get(['room_id', 'room_name']);

        if ($rooms->isEmpty()) {
            return 'No rooms found.';
        }

        $lines = ["Room occupancy — {$period} ({$start} to {$end}):"];

        foreach ($rooms as $room) {
            $bookedCount = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->where('room_id', $room->room_id)
                ->whereBetween('date', [$start, $end])
                ->whereIn('status', ['approved', 'completed'])
                ->count();

            $rate    = $totalSlots > 0 ? round($bookedCount / $totalSlots * 100, 1) : 0;
            $bar     = str_repeat('█', (int) ($rate / 10)) . str_repeat('░', 10 - (int) ($rate / 10));
            $lines[] = "  {$room->room_name}: {$bookedCount}/{$totalSlots} bookings [{$bar}] {$rate}%";
        }

        return implode("\n", $lines);
    }

    private function periodMeta(Carbon $now, string $period): array
    {
        return match ($period) {
            'today'      => [$now->toDateString(), $now->toDateString(), 1],
            'this_week'  => [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString(), 7],
            'this_month' => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString(), $now->daysInMonth],
            default      => [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString(), $now->dayOfYear],
        };
    }
}
