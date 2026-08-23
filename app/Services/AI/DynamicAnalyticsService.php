<?php

namespace App\Services\AI;

use App\Models\BookingRoom;
use App\Models\Delivery;
use App\Models\Guestbook;
use App\Models\VehicleBooking;
use App\Services\AI\Enums\ChatDataSource;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DynamicAnalyticsService
{
    private string $tz = 'Asia/Jakarta';

    public function __construct(private ?CsvDataReader $csvReader = null)
    {
        $this->csvReader = $csvReader ?? app(CsvDataReader::class);
    }

    /**
     * Compute dynamic aggregations based on structured parameters.
     */
    public function aggregate(int $companyId, array $params): array
    {
        $entity = strtolower((string) ($params['entity'] ?? 'vehicle_bookings'));
        $operation = strtolower((string) ($params['operation'] ?? 'count'));
        $year = isset($params['year']) ? (int) $params['year'] : Carbon::now($this->tz)->year;
        $weekday = isset($params['weekday']) ? ucfirst(strtolower(trim((string) $params['weekday']))) : null;
        $includeZeroPeriods = (bool) ($params['include_zero_periods'] ?? true);
        $sourcePreference = strtolower((string) ($params['data_source'] ?? $params['source'] ?? 'combined_auto'));
        if ($sourcePreference === 'auto') {
            $sourcePreference = 'combined_auto';
        }

        // If user is explicitly asking for server CSV analytics overview
        if ($sourcePreference === 'server_csv' && empty($weekday) && ($operation === 'summary' || $operation === 'count' || empty($params['operation']))) {
            return $this->csvReader->getComprehensiveServerCsvSummary($params['start_date'] ?? null, $params['end_date'] ?? null);
        }

        if ($weekday !== null && in_array($operation, ['average', 'mean', 'avg', 'count', 'weekday_occurrence', 'summary'], true)) {
            return $this->calculateWeekdayAverage($companyId, $entity, $weekday, $year, $includeZeroPeriods, $sourcePreference);
        }

        if (in_array($operation, ['weekday_breakdown', 'busiest_weekday'], true)) {
            return $this->calculateAllWeekdaysBreakdown($companyId, $entity, $year, $sourcePreference);
        }

        if (in_array($operation, ['monthly_breakdown', 'busiest_month'], true)) {
            return $this->calculateMonthlyBreakdown($companyId, $entity, $year, $sourcePreference);
        }

        if ($operation === 'summary') {
            if ($sourcePreference === 'server_csv') {
                return $this->csvReader->getComprehensiveServerCsvSummary($params['start_date'] ?? null, $params['end_date'] ?? null);
            }
        }

        return $this->calculateGeneralAggregate($companyId, $entity, $params);
    }

    /**
     * Calculate average bookings on a specific weekday across a year (e.g. Sundays in 2026).
     */
    public function calculateWeekdayAverage(
        int $companyId,
        string $entity,
        string $weekday,
        int $year,
        bool $includeZeroPeriods = true,
        string $sourcePreference = 'combined_auto'
    ): array {
        if ($sourcePreference === 'auto') {
            $sourcePreference = 'combined_auto';
        }

        // Handle SERVER_CSV routing
        if ($sourcePreference === 'server_csv') {
            $res = $this->csvReader->getWeekdayAverageFromCsv($entity, $weekday, $year);
            $res['sources'] = [$this->csvReader->getCsvSourceMetadata()];
            $res['source_type'] = 'server_csv';
            $res['text'] .= "\n\n" . ChatDataSource::formatSourcesTag($res['sources']);
            return $res;
        }

        // Handle COMBINED_AUTO and COMBINED routing
        if ($sourcePreference === 'combined_auto' || $sourcePreference === 'combined') {
            $liveRes = $this->calculateWeekdayAverage($companyId, $entity, $weekday, $year, $includeZeroPeriods, 'end_to_end');
            $csvRes = $this->csvReader->getWeekdayAverageFromCsv($entity, $weekday, $year);

            // Determine if CSV has matching data
            $hasCsvData = ($csvRes['success'] ?? false) && ($csvRes['period_count'] ?? 0) > 0;
            $hasLiveData = ($liveRes['success'] ?? false) && (($liveRes['total_bookings'] ?? 0) > 0 || ($liveRes['period_count'] ?? 0) > 0);

            // If only CSV has records (e.g. historical years where live DB has 0 records)
            if ($hasCsvData && ! $hasLiveData) {
                return $this->calculateWeekdayAverage($companyId, $entity, $weekday, $year, $includeZeroPeriods, 'server_csv');
            }

            // If both sources exist, present a combined-auto breakdown with clear provenance and no duplicate counting
            if ($hasCsvData && $hasLiveData) {
                $sources = [
                    [
                        'type'        => ChatDataSource::END_TO_END->value,
                        'label'       => ChatDataSource::END_TO_END->label(),
                        'description' => ChatDataSource::END_TO_END->description(),
                    ],
                    $this->csvReader->getCsvSourceMetadata(),
                ];

                $entityLabel = str_contains($entity, 'room') ? 'room bookings' : 'vehicle bookings';
                $standardWeekdayName = ucfirst(strtolower($weekday));

                $text = "### Combined Data Analysis for {$standardWeekdayName}s in {$year}\n\n"
                      . "• **Live System Records:** Average **{$liveRes['average']} {$entityLabel} per {$standardWeekdayName}** ({$liveRes['total_bookings']} qualifying bookings across {$liveRes['period_count']} {$standardWeekdayName}s, including {$liveRes['zero_booking_period_count']} {$standardWeekdayName}s with zero bookings).\n"
                      . "• **Server Historical CSV Baseline:** Average **{$csvRes['average']} {$entityLabel} per {$standardWeekdayName}** ({$csvRes['total_metric_value']} total recorded across {$csvRes['period_count']} historical {$standardWeekdayName}s).\n\n"
                      . ChatDataSource::formatSourcesTag($sources);

                return [
                    'success'                      => true,
                    'source_type'                  => 'combined',
                    'metric'                       => "average_{$entity}_on_{$standardWeekdayName}",
                    'entity'                       => $entity,
                    'weekday'                      => $standardWeekdayName,
                    'year'                         => $year,
                    'total_bookings'               => $liveRes['total_bookings'] ?? 0,
                    'period_count'                 => $liveRes['period_count'] ?? 0,
                    'zero_booking_period_count'    => $liveRes['zero_booking_period_count'] ?? 0,
                    'active_period_count'          => $liveRes['active_period_count'] ?? 0,
                    'average'                      => $liveRes['average'] ?? 0.0,
                    'live_data'                    => $liveRes,
                    'csv_data'                     => $csvRes,
                    'sources'                      => $sources,
                    'text'                         => $text,
                ];
            }

            // Otherwise, return Live result
            return $liveRes;
        }

        // END_TO_END routing
        $now = Carbon::now($this->tz);
        $weekdayMap = [
            'Sunday' => Carbon::SUNDAY,
            'Monday' => Carbon::MONDAY,
            'Tuesday' => Carbon::TUESDAY,
            'Wednesday' => Carbon::WEDNESDAY,
            'Thursday' => Carbon::THURSDAY,
            'Friday' => Carbon::FRIDAY,
            'Saturday' => Carbon::SATURDAY,
            'Minggu' => Carbon::SUNDAY,
            'Senin' => Carbon::MONDAY,
            'Selasa' => Carbon::TUESDAY,
            'Rabu' => Carbon::WEDNESDAY,
            'Kamis' => Carbon::THURSDAY,
            'Jumat' => Carbon::FRIDAY,
            'Sabtu' => Carbon::SATURDAY,
        ];

        $targetDayOfWeek = $weekdayMap[$weekday] ?? Carbon::SUNDAY;
        $standardWeekdayName = Carbon::createFromDate(2026, 1, 1)->next($targetDayOfWeek)->format('l');

        $yearStart = Carbon::create($year, 1, 1, 0, 0, 0, $this->tz);
        $yearEnd = Carbon::create($year, 12, 31, 23, 59, 59, $this->tz);

        // Generate all matching weekdays in the year
        $matchingDates = [];
        $cursor = $yearStart->copy();
        while ($cursor->dayOfWeek !== $targetDayOfWeek) {
            $cursor->addDay();
        }
        while ($cursor->lte($yearEnd)) {
            $matchingDates[] = $cursor->toDateString();
            $cursor->addWeek();
        }

        $totalPeriods = count($matchingDates);

        // Fetch qualifying booking counts grouped by date
        $countsByDate = $this->fetchCountsByDates($companyId, $entity, $matchingDates, $yearStart, $yearEnd);

        $totalBookings = 0;
        $activePeriods = 0;
        $zeroPeriods = 0;

        foreach ($matchingDates as $date) {
            $count = $countsByDate[$date] ?? 0;
            $totalBookings += $count;
            if ($count > 0) {
                $activePeriods++;
            } else {
                $zeroPeriods++;
            }
        }

        $denominator = $includeZeroPeriods ? $totalPeriods : max(1, $activePeriods);
        $average = $denominator > 0 ? round($totalBookings / $denominator, 2) : 0.0;

        $entityLabel = str_contains($entity, 'room') ? 'room bookings' : 'vehicle bookings';

        $sources = [
            [
                'type'        => ChatDataSource::END_TO_END->value,
                'label'       => ChatDataSource::END_TO_END->label(),
                'description' => ChatDataSource::END_TO_END->description(),
            ]
        ];

        $explanation = sprintf(
            "The average was **%s %s per %s** in %d. This was calculated from **%d qualifying bookings across %d %ss**, including %d %ss with zero bookings.\n\n%s",
            number_format($average, 2),
            $entityLabel,
            $standardWeekdayName,
            $year,
            $totalBookings,
            $totalPeriods,
            $standardWeekdayName,
            $zeroPeriods,
            $standardWeekdayName,
            ChatDataSource::formatSourcesTag($sources)
        );

        return [
            'success'                      => true,
            'source_type'                  => 'end_to_end',
            'metric'                       => "average_{$entity}_on_{$standardWeekdayName}",
            'entity'                       => $entity,
            'weekday'                      => $standardWeekdayName,
            'year'                         => $year,
            'total_bookings'               => $totalBookings,
            'period_count'                 => $totalPeriods,
            'active_period_count'          => $activePeriods,
            'zero_booking_period_count'    => $zeroPeriods,
            'included_zero_booking_periods'=> $includeZeroPeriods,
            'average'                      => $average,
            'calculation'                  => [
                'formula'     => 'total_bookings / period_count',
                'numerator'   => $totalBookings,
                'denominator' => $denominator,
                'result'      => $average,
            ],
            'sources'                      => $sources,
            'text'                         => $explanation,
        ];
    }

    /**
     * Calculate breakdown for all weekdays in a year.
     */
    public function calculateAllWeekdaysBreakdown(int $companyId, string $entity, int $year, string $sourcePreference = 'auto'): array
    {
        $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $results = [];
        $busiestDay = null;
        $maxAvg = -1.0;

        foreach ($weekdays as $day) {
            $stat = $this->calculateWeekdayAverage($companyId, $entity, $day, $year, true, $sourcePreference);
            $results[$day] = [
                'total_bookings' => $stat['total_bookings'] ?? $stat['total_metric_value'] ?? 0,
                'period_count'   => $stat['period_count'],
                'average'        => $stat['average'],
            ];

            if ($stat['average'] > $maxAvg) {
                $maxAvg = $stat['average'];
                $busiestDay = $day;
            }
        }

        $sources = [
            $sourcePreference === 'server_csv'
                ? $this->csvReader->getCsvSourceMetadata()
                : [
                    'type'        => ChatDataSource::END_TO_END->value,
                    'label'       => ChatDataSource::END_TO_END->label(),
                    'description' => ChatDataSource::END_TO_END->description(),
                ]
        ];

        $lines = ["Weekday Breakdown for {$entity} in {$year}:"];
        foreach ($results as $day => $data) {
            $lines[] = sprintf("  - %s: %d total bookings across %d days (Avg: %.2f/day)", $day, $data['total_bookings'], $data['period_count'], $data['average']);
        }
        $lines[] = sprintf("Busiest weekday: **%s** (average %.2f bookings per %s).", $busiestDay, $maxAvg, $busiestDay);
        $lines[] = "";
        $lines[] = ChatDataSource::formatSourcesTag($sources);

        return [
            'success'     => true,
            'year'        => $year,
            'entity'      => $entity,
            'busiest_day' => $busiestDay,
            'breakdown'   => $results,
            'sources'     => $sources,
            'text'        => implode("\n", $lines),
        ];
    }

    /**
     * Calculate monthly breakdown in a year.
     */
    public function calculateMonthlyBreakdown(int $companyId, string $entity, int $year, string $sourcePreference = 'auto'): array
    {
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        $results = [];
        $busiestMonth = null;
        $maxCount = -1;

        foreach ($months as $num => $name) {
            $start = Carbon::create($year, $num, 1, 0, 0, 0, $this->tz)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $count = $this->queryEntityCount($companyId, $entity, $start, $end);
            $results[$name] = $count;

            if ($count > $maxCount) {
                $maxCount = $count;
                $busiestMonth = $name;
            }
        }

        $sources = [
            [
                'type'        => ChatDataSource::END_TO_END->value,
                'label'       => ChatDataSource::END_TO_END->label(),
                'description' => ChatDataSource::END_TO_END->description(),
            ]
        ];

        $lines = ["Monthly Breakdown for {$entity} in {$year}:"];
        foreach ($results as $name => $count) {
            $lines[] = sprintf("  - %s: %d bookings", $name, $count);
        }
        $lines[] = sprintf("Month with most bookings: **%s** (%d bookings).", $busiestMonth, $maxCount);
        $lines[] = "";
        $lines[] = ChatDataSource::formatSourcesTag($sources);

        return [
            'success'       => true,
            'year'          => $year,
            'entity'        => $entity,
            'busiest_month' => $busiestMonth,
            'breakdown'     => $results,
            'sources'       => $sources,
            'text'          => implode("\n", $lines),
        ];
    }

    /**
     * Query count for a date range with proper status rules.
     */
    private function queryEntityCount(int $companyId, string $entity, Carbon $start, Carbon $end): int
    {
        if (str_contains($entity, 'room')) {
            return BookingRoom::where('company_id', $companyId)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->count();
        }

        if (str_contains($entity, 'guest')) {
            return Guestbook::where('company_id', $companyId)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->count();
        }

        if (str_contains($entity, 'deliver')) {
            return Delivery::where('company_id', $companyId)
                ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->count();
        }

        // Default: vehicle bookings
        return VehicleBooking::where('company_id', $companyId)
            ->whereBetween('start_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->count();
    }

    /**
     * Fetch grouped counts by date.
     */
    private function fetchCountsByDates(int $companyId, string $entity, array $dates, Carbon $yearStart, Carbon $yearEnd): array
    {
        $counts = [];

        if (str_contains($entity, 'room')) {
            $records = BookingRoom::where('company_id', $companyId)
                ->whereBetween('date', [$yearStart->toDateString(), $yearEnd->toDateString()])
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->selectRaw('DATE(date) as booking_date, COUNT(*) as cnt')
                ->groupBy('booking_date')
                ->pluck('cnt', 'booking_date')
                ->toArray();
        } else {
            // Vehicle bookings
            $records = VehicleBooking::where('company_id', $companyId)
                ->whereBetween('start_at', [$yearStart->toDateTimeString(), $yearEnd->toDateTimeString()])
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->selectRaw('DATE(start_at) as booking_date, COUNT(*) as cnt')
                ->groupBy('booking_date')
                ->pluck('cnt', 'booking_date')
                ->toArray();
        }

        foreach ($dates as $d) {
            $counts[$d] = (int) ($records[$d] ?? 0);
        }

        return $counts;
    }

    /**
     * Handle general aggregate filters.
     */
    private function calculateGeneralAggregate(int $companyId, string $entity, array $params): array
    {
        $year = isset($params['year']) ? (int) $params['year'] : Carbon::now($this->tz)->year;
        $start = Carbon::create($year, 1, 1, 0, 0, 0, $this->tz);
        $end = Carbon::create($year, 12, 31, 23, 59, 59, $this->tz);

        if (! empty($params['start_date']) && ! empty($params['end_date'])) {
            $start = Carbon::parse($params['start_date'], $this->tz)->startOfDay();
            $end = Carbon::parse($params['end_date'], $this->tz)->endOfDay();
        }

        $count = $this->queryEntityCount($companyId, $entity, $start, $end);

        $sources = [
            [
                'type'        => ChatDataSource::END_TO_END->value,
                'label'       => ChatDataSource::END_TO_END->label(),
                'description' => ChatDataSource::END_TO_END->description(),
            ]
        ];

        return [
            'success'    => true,
            'entity'     => $entity,
            'start_date' => $start->toDateString(),
            'end_date'   => $end->toDateString(),
            'count'      => $count,
            'sources'    => $sources,
            'text'       => sprintf("Total %s between %s and %s: %d.\n\n%s", $entity, $start->toDateString(), $end->toDateString(), $count, ChatDataSource::formatSourcesTag($sources)),
        ];
    }
}

