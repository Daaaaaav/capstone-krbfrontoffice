<?php

namespace App\Services\AI\Tools;

use App\Models\BookingRoom;
use App\Models\Delivery;
use App\Models\Guestbook;
use App\Models\VehicleBooking;
use App\Services\AI\Contracts\ToolInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tool: get_analytics
 *
 * Returns summarised booking/operational statistics for a given period.
 * Reads only — no writes. Results are cached briefly to avoid repeated
 * queries when the AI calls this multiple times in one session.
 */
class AnalyticsTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_analytics';
    }

    public function description(): string
    {
        return 'Retrieve booking and operational statistics for a time period. '
             . 'Use for questions about totals, trends, most-used rooms, peak hours, '
             . 'department usage, year-over-year comparisons, or any numerical summary.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'period' => [
                    'type'        => 'string',
                    'enum'        => ['today', 'this_week', 'this_month', 'this_year', 'last_month', 'last_year'],
                    'description' => 'The time period to analyse.',
                ],
                'module' => [
                    'type'        => 'string',
                    'enum'        => ['rooms', 'vehicles', 'guests', 'deliveries', 'all'],
                    'description' => 'Which module to include. Use "all" for a full summary.',
                ],
            ],
            'required' => ['period'],
        ];
    }

    public function execute(array $arguments): array
    {
        $companyId = Auth::user()?->company_id;
        $period    = $arguments['period'] ?? 'this_week';
        $module    = $arguments['module'] ?? 'all';

        [$start, $end] = $this->periodRange($period);
        $cacheKey = "ai_analytics_{$companyId}_{$period}_{$module}";

        $text = Cache::remember($cacheKey, 120, function () use ($companyId, $period, $start, $end, $module) {
            return $this->buildText($companyId, $period, $start, $end, $module);
        });

        return ['text' => $text];
    }

    private function buildText(?int $companyId, string $period, string $start, string $end, string $module): string
    {
        $lines  = ["Analytics — {$period} ({$start} to {$end}):"];
        $tz     = 'Asia/Jakarta';
        $now    = Carbon::now($tz);

        if (in_array($module, ['rooms', 'all'])) {
            $rQ        = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId));
            $total     = (clone $rQ)->whereBetween('created_at', [$start, $end])->count();
            $pending   = (clone $rQ)->whereBetween('created_at', [$start, $end])->where('status', 'pending')->count();
            $approved  = (clone $rQ)->whereBetween('created_at', [$start, $end])->where('status', 'approved')->count();
            $rejected  = (clone $rQ)->whereBetween('created_at', [$start, $end])->where('status', 'rejected')->count();
            $completed = (clone $rQ)->whereBetween('created_at', [$start, $end])->where('status', 'completed')->count();
            $rejRate   = $total > 0 ? round($rejected / $total * 100, 1) : 0;

            $topRoom = (clone $rQ)->whereBetween('created_at', [$start, $end])
                ->select('room_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('room_id')->orderByDesc('cnt')->with('room')->first();
            $topRoomName = $topRoom?->room?->room_name ?? 'N/A';

            $topDept = (clone $rQ)->whereBetween('created_at', [$start, $end])
                ->select('department_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('department_id')->orderByDesc('cnt')->with('department')->first();
            $topDeptName = $topDept?->department?->name ?? 'N/A';

            $peakHr  = (clone $rQ)->whereBetween('created_at', [$start, $end])
                ->selectRaw('HOUR(start_time) as hr, COUNT(*) as cnt')
                ->whereNotNull('start_time')->groupByRaw('HOUR(start_time)')->orderByDesc('cnt')->value('hr');
            $peakStr = $peakHr !== null ? sprintf('%02d:00–%02d:00', $peakHr, $peakHr + 1) : 'N/A';

            $lines[] = "ROOMS: total={$total} pending={$pending} approved={$approved} completed={$completed} rejected={$rejected} ({$rejRate}% rejection rate)";
            $lines[] = "  Most booked room: {$topRoomName} | Top dept: {$topDeptName} | Peak hour: {$peakStr}";
        }

        if (in_array($module, ['vehicles', 'all'])) {
            $vQ      = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId));
            $total   = (clone $vQ)->whereBetween('created_at', [$start, $end])->count();
            $pending = (clone $vQ)->whereBetween('created_at', [$start, $end])->where('status', 'pending')->count();
            $approved= (clone $vQ)->whereBetween('created_at', [$start, $end])->where('status', 'approved')->count();
            $rejected= (clone $vQ)->whereBetween('created_at', [$start, $end])->where('status', 'rejected')->count();
            $lines[] = "VEHICLES: total={$total} pending={$pending} approved={$approved} rejected={$rejected}";
        }

        if (in_array($module, ['guests', 'all'])) {
            $gQ    = Guestbook::when($companyId, fn($q) => $q->where('company_id', $companyId));
            $total = (clone $gQ)->whereBetween('created_at', [$start, $end])->count();
            $lines[] = "GUESTS: total={$total}";
        }

        if (in_array($module, ['deliveries', 'all'])) {
            $dQ      = Delivery::when($companyId, fn($q) => $q->where('company_id', $companyId));
            $total   = (clone $dQ)->whereBetween('created_at', [$start, $end])->count();
            $pending = (clone $dQ)->whereBetween('created_at', [$start, $end])->where('status', 'pending')->count();
            $lines[] = "DELIVERIES: total={$total} pending={$pending}";
        }

        return implode("\n", $lines);
    }

    private function periodRange(string $period): array
    {
        $tz  = 'Asia/Jakarta';
        $now = Carbon::now($tz);

        return match ($period) {
            'today'       => [$now->copy()->startOfDay()->toDateTimeString(),  $now->copy()->endOfDay()->toDateTimeString()],
            'this_week'   => [$now->copy()->startOfWeek()->toDateTimeString(), $now->copy()->endOfWeek()->toDateTimeString()],
            'this_month'  => [$now->copy()->startOfMonth()->toDateTimeString(),$now->copy()->endOfMonth()->toDateTimeString()],
            'last_month'  => [$now->copy()->subMonth()->startOfMonth()->toDateTimeString(), $now->copy()->subMonth()->endOfMonth()->toDateTimeString()],
            'last_year'   => [$now->copy()->subYear()->startOfYear()->toDateTimeString(),   $now->copy()->subYear()->endOfYear()->toDateTimeString()],
            default       => [$now->copy()->startOfYear()->toDateTimeString(),  $now->copy()->endOfYear()->toDateTimeString()],  // this_year
        };
    }
}
