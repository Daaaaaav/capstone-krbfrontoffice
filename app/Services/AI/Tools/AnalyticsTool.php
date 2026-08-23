<?php

namespace App\Services\AI\Tools;

use App\Models\BookingRoom;
use App\Models\Delivery;
use App\Models\Guestbook;
use App\Models\VehicleBooking;
use App\Services\AI\Contracts\ToolInterface;
use App\Services\AI\CsvDataReader;
use App\Services\AI\DynamicAnalyticsService;
use App\Services\AI\Enums\ChatDataSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsTool implements ToolInterface
{
    public function __construct(
        private ?DynamicAnalyticsService $dynamicService = null,
        private ?CsvDataReader $csvReader = null
    ) {
        $this->dynamicService = $dynamicService ?? app(DynamicAnalyticsService::class);
        $this->csvReader = $csvReader ?? app(CsvDataReader::class);
    }

    public function name(): string
    {
        return 'get_analytics';
    }

    public function description(): string
    {
        return 'Retrieve booking and operational statistics for a time period, or perform dynamic aggregations (weekday average, busiest day/month, breakdown by period). '
             . 'Supports Live System Data (end_to_end), Server Historical CSV (server_csv), or Combined comparison.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'period' => [
                    'type'        => 'string',
                    'enum'        => ['today', 'this_week', 'this_month', 'this_year', 'last_month', 'last_year'],
                    'description' => 'Optional predefined time period to analyse.',
                ],
                'module' => [
                    'type'        => 'string',
                    'enum'        => ['rooms', 'vehicles', 'guests', 'deliveries', 'cancellations', 'all'],
                    'description' => 'Which module to include. Use "cancellations" for cancellation-specific stats, or "all" for a full summary.',
                ],
                'entity' => [
                    'type'        => 'string',
                    'enum'        => ['vehicle_bookings', 'room_bookings', 'guests', 'deliveries'],
                    'description' => 'Target entity for dynamic aggregations.',
                ],
                'operation' => [
                    'type'        => 'string',
                    'enum'        => ['average', 'count', 'weekday_breakdown', 'monthly_breakdown', 'busiest_weekday', 'busiest_month'],
                    'description' => 'Type of dynamic aggregation to perform.',
                ],
                'weekday' => [
                    'type'        => 'string',
                    'description' => 'Weekday to filter (e.g. Sunday, Monday, etc.).',
                ],
                'year' => [
                    'type'        => 'integer',
                    'description' => 'Year to filter (e.g. 2026, 2025).',
                ],
                'include_zero_periods' => [
                    'type'        => 'boolean',
                    'description' => 'Whether zero-booking days should be included in the denominator for period averages (default true).',
                ],
                'data_source' => [
                    'type'        => 'string',
                    'enum'        => ['auto', 'end_to_end', 'server_csv', 'combined'],
                    'description' => 'Data source preference: "end_to_end" (live application records), "server_csv" (krb_historical_data.csv), or "combined" (comparison). Default is "auto".',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        $companyId = Auth::user()?->company_id;
        if (! $companyId) {
            return ['text' => 'Analytics data is currently unavailable.'];
        }

        // If dynamic aggregation parameters or specific data_source is specified, route through DynamicAnalyticsService
        if (isset($arguments['weekday']) || isset($arguments['entity']) || isset($arguments['data_source']) || (isset($arguments['operation']) && $arguments['operation'] !== 'count')) {
            $dynResult = $this->dynamicService->aggregate($companyId, $arguments);
            return [
                'text'    => $dynResult['text'] ?? json_encode($dynResult),
                'sources' => $dynResult['sources'] ?? [],
                'data'    => $dynResult,
            ];
        }

        $period = $arguments['period'] ?? 'this_week';
        $module = $arguments['module'] ?? 'all';

        [$start, $end] = $this->periodRange($period);
        $cacheKey = "ai_analytics_{$companyId}_{$period}_{$module}";

        $sources = [
            [
                'type'        => ChatDataSource::END_TO_END->value,
                'label'       => ChatDataSource::END_TO_END->label(),
                'description' => ChatDataSource::END_TO_END->description(),
            ]
        ];

        $text = Cache::remember($cacheKey, 120, function () use ($companyId, $period, $start, $end, $module, $sources) {
            return $this->buildText($companyId, $period, $start, $end, $module, $sources);
        });

        return [
            'text'    => $text,
            'sources' => $sources,
        ];
    }

    private function buildText(int $companyId, string $period, string $start, string $end, string $module, array $sources = []): string
    {
        $lines  = ["Analytics — {$period} ({$start} to {$end}):"];
        $tz     = 'Asia/Jakarta';
        $now    = Carbon::now($tz);

        if (in_array($module, ['rooms', 'cancellations', 'all'])) {
            $rQ        = BookingRoom::where('company_id', $companyId);
            $total     = (clone $rQ)->whereBetween('created_at', [$start, $end])->count();
            $pending   = (clone $rQ)->whereBetween('created_at', [$start, $end])->where('status', 'pending')->count();
            $approved  = (clone $rQ)->whereBetween('created_at', [$start, $end])->where('status', 'approved')->count();
            $rejected  = (clone $rQ)->whereBetween('created_at', [$start, $end])->where('status', 'rejected')->count();
            $cancelled = (clone $rQ)->whereBetween('created_at', [$start, $end])->where('status', 'cancelled')->count();
            $completed = (clone $rQ)->whereBetween('created_at', [$start, $end])->whereIn('status', ['completed', 'done'])->count();
            $rejRate   = $total > 0 ? round($rejected / $total * 100, 1) : 0;
            $canRate   = $total > 0 ? round($cancelled / $total * 100, 1) : 0;

            $topRoom = (clone $rQ)->whereBetween('created_at', [$start, $end])
                ->select('room_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('room_id')->orderByDesc('cnt')->with('room')->first();
            $topRoomName = $topRoom?->room?->room_name ?? 'N/A';

            $topDept = (clone $rQ)->whereBetween('created_at', [$start, $end])
                ->select('department_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('department_id')->orderByDesc('cnt')->with('department')->first();
            $topDeptName = $topDept?->department?->name ?? 'N/A';

            $peakHr  = (clone $rQ)->whereBetween('created_at', [$start, $end])
                ->selectRaw('strftime("%H", start_time) as hr, COUNT(*) as cnt')
                ->whereNotNull('start_time')->groupByRaw('hr')->orderByDesc('cnt')->value('hr');
            $peakStr = $peakHr !== null ? sprintf('%02d:00–%02d:00', $peakHr, $peakHr + 1) : 'N/A';

            $lines[] = "ROOMS: total={$total} pending={$pending} approved={$approved} completed={$completed} rejected={$rejected} cancelled={$cancelled} ({$canRate}% cancellation rate, {$rejRate}% rejection rate)";
            $lines[] = "  Most booked room: {$topRoomName} | Top dept: {$topDeptName} | Peak hour: {$peakStr}";
        }

        if (in_array($module, ['vehicles', 'cancellations', 'all'])) {
            $vQ        = VehicleBooking::where('company_id', $companyId);
            $total     = (clone $vQ)->whereBetween('created_at', [$start, $end])->count();
            $pending   = (clone $vQ)->whereBetween('created_at', [$start, $end])->where('status', 'pending')->count();
            $approved  = (clone $vQ)->whereBetween('created_at', [$start, $end])->where('status', 'approved')->count();
            $onProgress= (clone $vQ)->whereBetween('created_at', [$start, $end])->whereIn('status', ['on_progress', 'late_return'])->count();
            $completed = (clone $vQ)->whereBetween('created_at', [$start, $end])->whereIn('status', ['completed', 'returned'])->count();
            $rejected  = (clone $vQ)->whereBetween('created_at', [$start, $end])->where('status', 'rejected')->count();
            $cancelled = (clone $vQ)->whereBetween('created_at', [$start, $end])->where('status', 'cancelled')->count();
            $canRate   = $total > 0 ? round($cancelled / $total * 100, 1) : 0;
            $lines[]   = "VEHICLES: total={$total} pending={$pending} approved={$approved} on_progress={$onProgress} completed={$completed} rejected={$rejected} cancelled={$cancelled} ({$canRate}% cancellation rate)";
        }

        if (in_array($module, ['guests', 'all'])) {
            $gQ    = Guestbook::where('company_id', $companyId);
            $total = (clone $gQ)->whereBetween('created_at', [$start, $end])->count();
            $lines[] = "GUESTS: total={$total}";
        }

        if (in_array($module, ['deliveries', 'all'])) {
            $dQ      = Delivery::where('company_id', $companyId);
            $total   = (clone $dQ)->whereBetween('created_at', [$start, $end])->count();
            $pending = (clone $dQ)->whereBetween('created_at', [$start, $end])->where('status', 'pending')->count();
            $lines[] = "DELIVERIES: total={$total} pending={$pending}";
        }

        if (! empty($sources)) {
            $lines[] = "";
            $lines[] = ChatDataSource::formatSourcesTag($sources);
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
