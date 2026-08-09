<?php

namespace App\Services;

use App\Models\BookingRoom;
use App\Models\VehicleBooking;
use App\Models\Delivery;
use App\Models\Guestbook;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsExportService
{
    private string $tz = 'Asia/Jakarta';

    public function build(?int $companyId): array
    {
        $now       = Carbon::now($this->tz);
        $year      = $now->year;
        $prevYear  = $year - 1;

        $yearStart = $now->copy()->startOfYear()->toDateTimeString();
        $yearEnd   = $now->copy()->endOfYear()->toDateTimeString();
        $prevStart = $now->copy()->subYear()->startOfYear()->toDateTimeString();
        $prevEnd   = $now->copy()->subYear()->endOfYear()->toDateTimeString();
        $weekStart = $now->copy()->startOfWeek()->toDateString();
        $weekEnd   = $now->copy()->endOfWeek()->toDateString();
        $today     = $now->toDateString();

        $rq = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId));

        $rooms = [
            'ytd_total'      => (clone $rq)->whereBetween('created_at', [$yearStart, $yearEnd])->count(),
            'ytd_pending'    => (clone $rq)->where('status', 'pending')->count(),
            'ytd_approved'   => (clone $rq)->where('status', 'approved')->count(),
            'ytd_completed'  => (clone $rq)->where('status', 'completed')->count(),
            'ytd_rejected'   => (clone $rq)->where('status', 'rejected')->count(),
            'prev_year'      => (clone $rq)->whereBetween('created_at', [$prevStart, $prevEnd])->count(),
            'today'          => (clone $rq)->whereDate('date', $today)->count(),
            'week_total'     => (clone $rq)->whereBetween('date', [$weekStart, $weekEnd])->count(),
            'week_pending'   => (clone $rq)->whereBetween('date', [$weekStart, $weekEnd])->where('status', 'pending')->count(),
            'week_approved'  => (clone $rq)->whereBetween('date', [$weekStart, $weekEnd])->where('status', 'approved')->count(),
            'week_completed' => (clone $rq)->whereBetween('date', [$weekStart, $weekEnd])->where('status', 'completed')->count(),
            'week_rejected'  => (clone $rq)->whereBetween('date', [$weekStart, $weekEnd])->where('status', 'rejected')->count(),
        ];

        $rooms['rejection_rate'] = $rooms['ytd_total'] > 0
            ? round($rooms['ytd_rejected'] / $rooms['ytd_total'] * 100, 1)
            : 0;

        $rooms['yoy_change'] = $this->yoyChange($rooms['prev_year'], $rooms['ytd_total']);

        $topRoom = (clone $rq)
            ->select('room_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('room_id')->orderByDesc('cnt')->with('room')->first();
        $rooms['top_room'] = $topRoom?->room?->room_name ?? 'N/A';

        $topRoomWeek = (clone $rq)
            ->select('room_id', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->groupBy('room_id')->orderByDesc('cnt')->with('room')->first();
        $rooms['top_room_week'] = $topRoomWeek?->room?->room_name ?? 'N/A';

        $topDept = (clone $rq)
            ->select('department_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('department_id')->orderByDesc('cnt')->with('department')->first();
        $rooms['top_dept'] = $topDept?->department?->name ?? 'N/A';

        $topDeptWeek = (clone $rq)
            ->select('department_id', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->groupBy('department_id')->orderByDesc('cnt')->with('department')->first();
        $rooms['top_dept_week'] = $topDeptWeek?->department?->name ?? 'N/A';

        $peakHr = (clone $rq)
            ->selectRaw('HOUR(start_time) as hr, COUNT(*) as cnt')
            ->whereNotNull('start_time')
            ->groupByRaw('HOUR(start_time)')->orderByDesc('cnt')->value('hr');
        $rooms['peak_hour'] = $peakHr !== null
            ? sprintf('%02d:00–%02d:00', $peakHr, $peakHr + 1)
            : 'N/A';

        $rooms['monthly'] = $this->monthlyBreakdown(
            $rq, 'created_at', $yearStart, $yearEnd
        );

        $vq = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId));

        $vehicles = [
            'ytd_total'    => (clone $vq)->whereBetween('created_at', [$yearStart, $yearEnd])->count(),
            'ytd_pending'  => (clone $vq)->where('status', 'pending')->count(),
            'ytd_approved' => (clone $vq)->where('status', 'approved')->count(),
            'ytd_rejected' => (clone $vq)->where('status', 'rejected')->count(),
            'prev_year'    => (clone $vq)->whereBetween('created_at', [$prevStart, $prevEnd])->count(),
            'today'        => (clone $vq)->whereDate('start_at', $today)->count(),
            'week_total'   => (clone $vq)->whereBetween('start_at', [$weekStart . ' 00:00:00', $weekEnd . ' 23:59:59'])->count(),
        ];

        $vehicles['yoy_change'] = $this->yoyChange($vehicles['prev_year'], $vehicles['ytd_total']);
        $vehicles['rejection_rate'] = $vehicles['ytd_total'] > 0
            ? round($vehicles['ytd_rejected'] / $vehicles['ytd_total'] * 100, 1)
            : 0;

        $vehicles['monthly'] = $this->monthlyBreakdown(
            $vq, 'created_at', $yearStart, $yearEnd
        );

        $dq = Delivery::when($companyId, fn($q) => $q->where('company_id', $companyId));

        $deliveries = [
            'ytd_total'   => (clone $dq)->whereBetween('created_at', [$yearStart, $yearEnd])->count(),
            'ytd_pending' => (clone $dq)->where('status', 'pending')->count(),
            'week_total'  => (clone $dq)->whereBetween('created_at', [$weekStart . ' 00:00:00', $weekEnd . ' 23:59:59'])->count(),
        ];

        $deliveries['monthly'] = $this->monthlyBreakdown(
            $dq, 'created_at', $yearStart, $yearEnd
        );

        $gq = Guestbook::when($companyId, fn($q) => $q->where('company_id', $companyId));

        $guests = [
            'ytd_total'  => (clone $gq)->whereBetween('created_at', [$yearStart, $yearEnd])->count(),
            'today'      => (clone $gq)->whereDate('date', $today)->count(),
            'week_total' => (clone $gq)->whereBetween('date', [$weekStart, $weekEnd])->count(),
        ];

        $guests['monthly'] = $this->monthlyBreakdown(
            $gq, 'created_at', $yearStart, $yearEnd
        );

        $flags = $this->buildFlags($rooms, $vehicles, $deliveries);

        return [
            'generated_at' => $now->format('d M Y, H:i') . ' WIB',
            'period_week'  => $weekStart . ' – ' . $weekEnd,
            'period_ytd'   => '1 Jan ' . $year . ' – ' . $now->format('d M Y'),
            'year'         => $year,
            'prev_year'    => $prevYear,
            'rooms'        => $rooms,
            'vehicles'     => $vehicles,
            'deliveries'   => $deliveries,
            'guests'       => $guests,
            'flags'        => $flags,
            'months'       => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        ];
    }

    private function monthlyBreakdown($query, string $dateCol, string $start, string $end): array
    {
        $rows = (clone $query)
            ->selectRaw("MONTH($dateCol) as mo, COUNT(*) as cnt")
            ->whereBetween($dateCol, [$start, $end])
            ->groupByRaw("MONTH($dateCol)")
            ->pluck('cnt', 'mo')
            ->toArray();

        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $result[$m] = $rows[$m] ?? 0;
        }
        return $result;
    }

    private function yoyChange(int $prev, int $current): ?float
    {
        if ($prev === 0) {
            return null;
        }
        return round(($current - $prev) / $prev * 100, 1);
    }

    private function buildFlags(array $rooms, array $vehicles, array $deliveries): array
    {
        $flags = [];

        if ($rooms['rejection_rate'] >= 20) {
            $flags[] = [
                'level'    => 'warning',
                'category' => 'Room Bookings',
                'message'  => "Rejection rate is {$rooms['rejection_rate']}% YTD — investigate recurring reasons for rejections (conflicts, policy, capacity).",
            ];
        } elseif ($rooms['rejection_rate'] >= 10) {
            $flags[] = [
                'level'    => 'info',
                'category' => 'Room Bookings',
                'message'  => "Rejection rate is {$rooms['rejection_rate']}% YTD — monitor for upward trend.",
            ];
        }

        if ($rooms['ytd_pending'] >= 5) {
            $flags[] = [
                'level'    => 'warning',
                'category' => 'Room Bookings',
                'message'  => "{$rooms['ytd_pending']} room bookings are currently pending approval — review and clear the backlog.",
            ];
        }

        if ($vehicles['rejection_rate'] >= 20) {
            $flags[] = [
                'level'    => 'warning',
                'category' => 'Vehicle Bookings',
                'message'  => "Vehicle rejection rate is {$vehicles['rejection_rate']}% YTD — review approval criteria and vehicle availability.",
            ];
        }

        if ($vehicles['ytd_pending'] >= 5) {
            $flags[] = [
                'level'    => 'warning',
                'category' => 'Vehicle Bookings',
                'message'  => "{$vehicles['ytd_pending']} vehicle bookings are pending — process approvals to avoid scheduling conflicts.",
            ];
        }

        if ($deliveries['ytd_pending'] >= 10) {
            $flags[] = [
                'level'    => 'warning',
                'category' => 'Deliveries',
                'message'  => "{$deliveries['ytd_pending']} delivery documents are pending — follow up to prevent backlog.",
            ];
        }
        
        if ($rooms['yoy_change'] !== null) {
            if ($rooms['yoy_change'] > 30) {
                $flags[] = [
                    'level'    => 'info',
                    'category' => 'Room Bookings',
                    'message'  => "Room booking volume up {$rooms['yoy_change']}% vs last year — ensure capacity keeps pace with demand.",
                ];
            } elseif ($rooms['yoy_change'] < -20) {
                $flags[] = [
                    'level'    => 'warning',
                    'category' => 'Room Bookings',
                    'message'  => "Room booking volume down {$rooms['yoy_change']}% vs last year — investigate cause of declining usage.",
                ];
            }
        }

        if ($vehicles['yoy_change'] !== null) {
            if ($vehicles['yoy_change'] < -20) {
                $flags[] = [
                    'level'    => 'warning',
                    'category' => 'Vehicle Bookings',
                    'message'  => "Vehicle usage down {$vehicles['yoy_change']}% vs last year — check if fleet capacity or policy is a barrier.",
                ];
            }
        }

        if (empty($flags)) {
            $flags[] = [
                'level'    => 'ok',
                'category' => 'Overall',
                'message'  => 'No critical issues detected. All metrics are within normal ranges.',
            ];
        }

        return $flags;
    }
}
