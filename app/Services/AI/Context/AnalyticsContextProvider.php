<?php

namespace App\Services\AI\Context;

use App\Models\BookingRoom;
use App\Models\Delivery;
use App\Models\Guestbook;
use App\Models\VehicleBooking;
use App\Services\AI\Contracts\ContextProviderInterface;
use App\Services\AI\Enums\ContextDetailLevel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsContextProvider implements ContextProviderInterface
{
    private string $tz = 'Asia/Jakarta';

    public function name(): string
    {
        return 'analytics';
    }

    public function load(?int $companyId, array $params = [], ?ContextDetailLevel $detailLevel = null): string
    {
        if (! $companyId) {
            return '(no analytics data available: company not specified)';
        }

        $period   = $params['period'] ?? 'this_week_and_ytd';
        $level    = $detailLevel ?? ContextDetailLevel::DETAILED;
        $weekday  = $params['weekday'] ?? null;
        $year     = $params['year'] ?? null;
        $message  = $params['message'] ?? $params['query'] ?? '';

        $resolver = app(\App\Services\AI\DataSourceResolver::class);
        $sourcePref = $resolver->detectSourcePreference($message);

        $cacheKey = "ctx_analytics_{$companyId}_{$period}_{$level->value}_{$weekday}_{$year}_{$sourcePref->value}";

        return Cache::remember($cacheKey, 120, function () use ($companyId, $level, $params, $weekday, $sourcePref) {
            $dynService = app(\App\Services\AI\DynamicAnalyticsService::class);
            $csvReader = app(\App\Services\AI\CsvDataReader::class);
            $year = $params['year'] ?? Carbon::now($this->tz)->year;

            // 1. If explicit SERVER_CSV requested
            if ($sourcePref === \App\Services\AI\Enums\ChatDataSource::SERVER_CSV) {
                $csvSummary = $csvReader->getComprehensiveServerCsvSummary();
                $base = "=== SERVER HISTORICAL CSV ANALYTICS (krb_historical_data.csv) ===\n\n" . $csvSummary['text'];

                if (! empty($weekday)) {
                    $vResult = $dynService->calculateWeekdayAverage($companyId, 'vehicle_bookings', $weekday, $year, true, 'server_csv');
                    $rResult = $dynService->calculateWeekdayAverage($companyId, 'room_bookings', $weekday, $year, true, 'server_csv');
                    $base .= "\n\n=== CSV DETERMINISTIC CALCULATION BREAKDOWN (" . ucfirst($weekday) . " in {$year}) ===\n"
                        . "• Vehicles: " . $vResult['text'] . "\n"
                        . "• Rooms: " . $rResult['text'];
                }

                return $base;
            }

            // 2. If explicit END_TO_END requested
            if ($sourcePref === \App\Services\AI\Enums\ChatDataSource::END_TO_END) {
                $base = $this->build($companyId, $level);

                if (! empty($weekday)) {
                    $vResult = $dynService->calculateWeekdayAverage($companyId, 'vehicle_bookings', $weekday, $year, true, 'end_to_end');
                    $rResult = $dynService->calculateWeekdayAverage($companyId, 'room_bookings', $weekday, $year, true, 'end_to_end');
                    $base .= "\n\n=== LIVE SYSTEM CALCULATION BREAKDOWN (" . ucfirst($weekday) . " in {$year}) ===\n"
                        . "• Vehicles: " . $vResult['text'] . "\n"
                        . "• Rooms: " . $rResult['text'];
                }

                return $base;
            }

            // 3. COMBINED_AUTO (Default)
            $base = $this->build($companyId, $level);

            // Include Server Historical CSV overview in detailed context
            if ($level === ContextDetailLevel::DETAILED || $level === ContextDetailLevel::NORMAL) {
                $csvSummary = $csvReader->getComprehensiveServerCsvSummary();
                if ($csvSummary['success'] ?? false) {
                    $base .= "\n\n=== SERVER HISTORICAL CSV OVERVIEW (krb_historical_data.csv) ===\n"
                        . "• Period: {$csvSummary['start_date']} to {$csvSummary['end_date']} (" . number_format($csvSummary['total_records']) . " daily records)\n"
                        . "• Total Visitors: " . number_format($csvSummary['visitors']['total']) . " (Daily avg: {$csvSummary['visitors']['daily_avg']})\n"
                        . "• Total Room Bookings: " . number_format($csvSummary['room_bookings']['total']) . " (Online: " . number_format($csvSummary['room_bookings']['online']) . ", Offline: " . number_format($csvSummary['room_bookings']['offline']) . ", avg: {$csvSummary['room_bookings']['daily_avg']}/day)\n"
                        . "• Total Vehicle Bookings: " . number_format($csvSummary['vehicle_bookings']['total']) . " (Daily avg: {$csvSummary['vehicle_bookings']['daily_avg']})\n"
                        . "• Documents/Packages: " . number_format($csvSummary['packages']['received_total']) . " received (avg: {$csvSummary['packages']['received_avg']}/day), " . number_format($csvSummary['packages']['sent_total']) . " sent\n"
                        . "• Source attribution: Server Historical CSV (krb_historical_data.csv)";
                }
            }

            if (! empty($weekday)) {
                $vResult = $dynService->calculateWeekdayAverage($companyId, 'vehicle_bookings', $weekday, $year, true, 'combined_auto');
                $rResult = $dynService->calculateWeekdayAverage($companyId, 'room_bookings', $weekday, $year, true, 'combined_auto');
                $base .= "\n\n=== DYNAMIC CALCULATION BREAKDOWN (" . ucfirst($weekday) . " in {$year}) ===\n"
                    . "• Vehicles: " . $vResult['text'] . "\n"
                    . "• Rooms: " . $rResult['text'];
            }

            return $base;
        });
    }

    private function build(int $companyId, ContextDetailLevel $level): string
    {
        return match ($level) {
            ContextDetailLevel::MINIMAL => $this->buildMinimal($companyId),
            ContextDetailLevel::NORMAL => $this->buildNormal($companyId),
            ContextDetailLevel::BOOKING => $this->buildNormal($companyId),
            ContextDetailLevel::DETAILED => $this->buildDetailed($companyId),
        };
    }

    private function buildMinimal(int $companyId): string
    {
        $now = Carbon::now($this->tz);
        $today = $now->toDateString();

        $rQ = BookingRoom::where('company_id', $companyId);
        $rToday = (clone $rQ)->whereDate('date', $today)->count();
        $rCancel = (clone $rQ)->whereDate('date', $today)->where('status', 'cancelled')->count();

        $vQ = VehicleBooking::where('company_id', $companyId);
        $vToday = (clone $vQ)->whereDate('start_at', $today)->count();
        $vCancel = (clone $vQ)->whereDate('start_at', $today)->where('status', 'cancelled')->count();

        return "TODAY: Rooms:{$rToday} (cancelled:{$rCancel}) | Vehicles:{$vToday} (cancelled:{$vCancel})";
    }

    private function buildNormal(int $companyId): string
    {
        $now = Carbon::now($this->tz);
        $weekStart = $now->copy()->startOfWeek()->toDateString();
        $weekEnd = $now->copy()->endOfWeek()->toDateString();
        $today = $now->toDateString();

        $rQ = BookingRoom::where('company_id', $companyId);
        $rWeek = (clone $rQ)->whereBetween('date', [$weekStart, $weekEnd])->count();
        $rWeekCancelled = (clone $rQ)->whereBetween('date', [$weekStart, $weekEnd])->where('status', 'cancelled')->count();
        $rToday = (clone $rQ)->whereDate('date', $today)->count();
        $rPending = (clone $rQ)->where('status', 'pending')->count();
        $rCancelledTotal = (clone $rQ)->where('status', 'cancelled')->count();

        $vQ = VehicleBooking::where('company_id', $companyId);
        $vWeek = (clone $vQ)->whereBetween('start_at', [$weekStart . ' 00:00:00', $weekEnd . ' 23:59:59'])->count();
        $vWeekCancelled = (clone $vQ)->whereBetween('start_at', [$weekStart . ' 00:00:00', $weekEnd . ' 23:59:59'])->where('status', 'cancelled')->count();
        $vPending = (clone $vQ)->where('status', 'pending')->count();
        $vCancelledTotal = (clone $vQ)->where('status', 'cancelled')->count();

        return <<<BLOCK
        WEEK ({$weekStart}–{$weekEnd}): Rooms:{$rWeek} (cancelled:{$rWeekCancelled}) | Vehicles:{$vWeek} (cancelled:{$vWeekCancelled})
        TODAY: Rooms:{$rToday} | pending:{$rPending}
        CANCELLATIONS TOTAL: Rooms:{$rCancelledTotal} | Vehicles:{$vCancelledTotal}
        Vehicles: pending:{$vPending}
        BLOCK;
    }

    private function buildDetailed(int $companyId): string
    {
        $now       = Carbon::now($this->tz);
        $yearStart = $now->copy()->startOfYear()->toDateTimeString();
        $yearEnd   = $now->copy()->endOfYear()->toDateTimeString();
        $prevStart = $now->copy()->subYear()->startOfYear()->toDateTimeString();
        $prevEnd   = $now->copy()->subYear()->endOfYear()->toDateTimeString();
        $weekStart = $now->copy()->startOfWeek()->toDateString();
        $weekEnd   = $now->copy()->endOfWeek()->toDateString();
        $monthStart= $now->copy()->startOfMonth()->toDateTimeString();
        $monthEnd  = $now->copy()->endOfMonth()->toDateTimeString();
        $today     = $now->toDateString();

        $rQ  = BookingRoom::where('company_id', $companyId);
        $rY  = (clone $rQ)->whereBetween('created_at', [$yearStart, $yearEnd]);
        $rP  = (clone $rQ)->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $rW  = (clone $rQ)->whereBetween('date', [$weekStart, $weekEnd]);
        $rM  = (clone $rQ)->whereBetween('created_at', [$monthStart, $monthEnd]);

        $rTotal      = $rY->count();
        $rPending    = (clone $rQ)->where('status', 'pending')->count();
        $rApproved   = (clone $rQ)->where('status', 'approved')->count();
        $rCompleted  = (clone $rQ)->whereIn('status', ['completed', 'done'])->count();
        $rRejected   = (clone $rQ)->where('status', 'rejected')->count();
        $rCancelled  = (clone $rQ)->where('status', 'cancelled')->count();
        $rMonthTotal = (clone $rM)->count();
        $rMonthCancelled = (clone $rM)->where('status', 'cancelled')->count();
        $rWeekCancelled  = (clone $rW)->where('status', 'cancelled')->count();
        $rToday      = (clone $rQ)->whereDate('date', $today)->count();
        $rWeek       = $rW->count();
        $rRej        = $rTotal > 0 ? round($rRejected / $rTotal * 100, 1) : 0;
        $rCancelRate = $rTotal > 0 ? round($rCancelled / $rTotal * 100, 1) : 0;
        $rTrend      = $this->trend($rP, $rTotal);

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

        $vQ          = VehicleBooking::where('company_id', $companyId);
        $vTotal      = (clone $vQ)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $vPrev       = (clone $vQ)->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $vWeek       = (clone $vQ)->whereBetween('start_at', [$weekStart . ' 00:00:00', $weekEnd . ' 23:59:59'])->count();
        $vPending    = (clone $vQ)->where('status', 'pending')->count();
        $vApproved   = (clone $vQ)->where('status', 'approved')->count();
        $vOnProgress = (clone $vQ)->whereIn('status', ['on_progress', 'late_return'])->count();
        $vCompleted  = (clone $vQ)->whereIn('status', ['completed', 'returned'])->count();
        $vRejected   = (clone $vQ)->where('status', 'rejected')->count();
        $vCancelled  = (clone $vQ)->where('status', 'cancelled')->count();
        $vM          = (clone $vQ)->whereBetween('created_at', [$monthStart, $monthEnd]);
        $vMonthTotal = (clone $vM)->count();
        $vMonthCancelled = (clone $vM)->where('status', 'cancelled')->count();
        $vWeekCancelled  = (clone $vQ)->whereBetween('start_at', [$weekStart . ' 00:00:00', $weekEnd . ' 23:59:59'])->where('status', 'cancelled')->count();
        $vCancelRate = $vTotal > 0 ? round($vCancelled / $vTotal * 100, 1) : 0;
        $vTrend      = $this->trend($vPrev, $vTotal);

        $gTotal  = Guestbook::where('company_id', $companyId)
            ->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $gToday  = Guestbook::where('company_id', $companyId)
            ->whereDate('date', $today)->count();
        $gWeek   = Guestbook::where('company_id', $companyId)
            ->whereBetween('date', [$weekStart, $weekEnd])->count();

        $dTotal  = Delivery::where('company_id', $companyId)
            ->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $dPend   = Delivery::where('company_id', $companyId)
            ->where('status', 'pending')->count();

        return <<<BLOCK
        === LIVE ANALYTICS ({$now->format('d M Y, H:i')} WIB) ===

        THIS WEEK ({$weekStart}–{$weekEnd}):
        Rooms:{$rWeek} (cancelled:{$rWeekCancelled}) | Vehicles:{$vWeek} (cancelled:{$vWeekCancelled}) | Guests:{$gWeek}
        Most booked room: {$topRoomName} | Top dept: {$topDeptName}

        THIS MONTH ({$now->format('M Y')}):
        Rooms: {$rMonthTotal} total (cancelled:{$rMonthCancelled}) | Vehicles: {$vMonthTotal} total (cancelled:{$vMonthCancelled})

        {$now->year} YEAR-TO-DATE:
        Rooms   : {$rTotal} (prev:{$rP}, {$rTrend}) | status: pending={$rPending} approved={$rApproved} completed={$rCompleted} rejected={$rRejected} cancelled={$rCancelled} ({$rCancelRate}% cancellation rate, {$rRej}% rejection rate)
          Today:{$rToday} | Peak hour:{$peakStr}
        Vehicles: {$vTotal} (prev:{$vPrev}, {$vTrend}) | status: pending={$vPending} approved={$vApproved} on_progress={$vOnProgress} completed={$vCompleted} rejected={$vRejected} cancelled={$vCancelled} ({$vCancelRate}% cancellation rate)
        Guests  : {$gTotal} total, today={$gToday}
        Deliveries: {$dTotal} total, pending={$dPend}

        CANCELLATION SUMMARY:
        - Room cancellations: {$rCancelled} YTD ({$rCancelRate}% rate), {$rMonthCancelled} this month, {$rWeekCancelled} this week.
        - Vehicle cancellations: {$vCancelled} YTD ({$vCancelRate}% rate), {$vMonthCancelled} this month, {$vWeekCancelled} this week.
        BLOCK;
    }

    private function trend(int $prev, int $curr): string
    {
        if ($prev === 0) return $curr > 0 ? '+100% new' : 'no change';
        $p = round(($curr - $prev) / $prev * 100, 1);
        return ($p >= 0 ? '+' : '') . $p . '%';
    }
}
