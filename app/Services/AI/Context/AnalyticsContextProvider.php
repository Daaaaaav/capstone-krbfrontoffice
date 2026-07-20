<?php

namespace App\Services\AI\Context;

use App\Models\BookingRoom;
use App\Models\Delivery;
use App\Models\Guestbook;
use App\Models\VehicleBooking;
use App\Services\AI\Contracts\ContextProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Loads analytics context — summaries and KPIs.
 * Used for both the Manager chatbot and when a receptionist asks a stats question.
 * Cached for 3 minutes to reduce repeated aggregation queries.
 */
class AnalyticsContextProvider implements ContextProviderInterface
{
    private string $tz = 'Asia/Jakarta';

    public function name(): string
    {
        return 'analytics';
    }

    public function load(?int $companyId, array $params = []): string
    {
        $period   = $params['period'] ?? 'this_week_and_ytd';
        $cacheKey = "ctx_analytics_{$companyId}_{$period}";
        return Cache::remember($cacheKey, 180, fn() => $this->build($companyId));
    }

    private function build(?int $companyId): string
    {
        $now       = Carbon::now($this->tz);
        $yearStart = $now->copy()->startOfYear()->toDateTimeString();
        $yearEnd   = $now->copy()->endOfYear()->toDateTimeString();
        $prevStart = $now->copy()->subYear()->startOfYear()->toDateTimeString();
        $prevEnd   = $now->copy()->subYear()->endOfYear()->toDateTimeString();
        $weekStart = $now->copy()->startOfWeek()->toDateString();
        $weekEnd   = $now->copy()->endOfWeek()->toDateString();
        $today     = $now->toDateString();

        // ── Rooms ────────────────────────────────────────────────
        $rQ  = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId));
        $rY  = (clone $rQ)->whereBetween('created_at', [$yearStart, $yearEnd]);
        $rP  = (clone $rQ)->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $rW  = (clone $rQ)->whereBetween('date', [$weekStart, $weekEnd]);

        $rTotal     = $rY->count();
        $rPending   = (clone $rQ)->where('status', 'pending')->count();
        $rApproved  = (clone $rQ)->where('status', 'approved')->count();
        $rCompleted = (clone $rQ)->where('status', 'completed')->count();
        $rRejected  = (clone $rQ)->where('status', 'rejected')->count();
        $rToday     = (clone $rQ)->whereDate('date', $today)->count();
        $rWeek      = $rW->count();
        $rRej       = $rTotal > 0 ? round($rRejected / $rTotal * 100, 1) : 0;
        $rTrend     = $this->trend($rP, $rTotal);

        $topRoom = (clone $rQ)->whereBetween('created_at', [$yearStart, $yearEnd])
            ->select('room_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('room_id')->orderByDesc('cnt')->with('room')->first();
        $topRoomName = $topRoom?->room?->room_name ?? 'N/A';

        $topDept = (clone $rQ)->whereBetween('created_at', [$yearStart, $yearEnd])
            ->select('department_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('department_id')->orderByDesc('cnt')->with('department')->first();
        $topDeptName = $topDept?->department?->name ?? 'N/A';

        $peakHr  = (clone $rQ)->whereBetween('created_at', [$yearStart, $yearEnd])
            ->selectRaw('HOUR(start_time) as hr, COUNT(*) as cnt')
            ->whereNotNull('start_time')->groupByRaw('HOUR(start_time)')->orderByDesc('cnt')->value('hr');
        $peakStr = $peakHr !== null ? sprintf('%02d:00–%02d:00', $peakHr, $peakHr + 1) : 'N/A';

        // ── Vehicles ─────────────────────────────────────────────
        $vQ       = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId));
        $vTotal   = (clone $vQ)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $vPrev    = (clone $vQ)->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $vWeek    = (clone $vQ)->whereBetween('start_at', [$weekStart . ' 00:00:00', $weekEnd . ' 23:59:59'])->count();
        $vPending = (clone $vQ)->where('status', 'pending')->count();
        $vTrend   = $this->trend($vPrev, $vTotal);

        // ── Guests / Deliveries ───────────────────────────────────
        $gTotal  = Guestbook::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $gToday  = Guestbook::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereDate('date', $today)->count();
        $gWeek   = Guestbook::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereBetween('date', [$weekStart, $weekEnd])->count();

        $dTotal  = Delivery::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $dPend   = Delivery::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->where('status', 'pending')->count();

        return <<<BLOCK
        === LIVE ANALYTICS ({$now->format('d M Y, H:i')} WIB) ===

        THIS WEEK ({$weekStart}–{$weekEnd}):
        Rooms:{$rWeek} | Vehicles:{$vWeek} | Guests:{$gWeek}
        Most booked room: {$topRoomName} | Top dept: {$topDeptName}

        {$now->year} YEAR-TO-DATE:
        Rooms   : {$rTotal} (prev:{$rP}, {$rTrend}) | status: pending={$rPending} approved={$rApproved} completed={$rCompleted} rejected={$rRejected} ({$rRej}% rejection)
          Today:{$rToday} | Peak hour:{$peakStr}
        Vehicles: {$vTotal} (prev:{$vPrev}, {$vTrend}) | pending={$vPending}
        Guests  : {$gTotal} total, today={$gToday}
        Deliveries: {$dTotal} total, pending={$dPend}
        BLOCK;
    }

    private function trend(int $prev, int $curr): string
    {
        if ($prev === 0) return $curr > 0 ? '+100% new' : 'no change';
        $p = round(($curr - $prev) / $prev * 100, 1);
        return ($p >= 0 ? '+' : '') . $p . '%';
    }
}
