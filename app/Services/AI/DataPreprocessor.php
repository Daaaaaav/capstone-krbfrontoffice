<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\AISettings;
use App\Models\BookingRoom;
use App\Models\VehicleBooking;
use App\Models\Guestbook;
use App\Models\Delivery;
use App\Models\User;

class DataPreprocessor
{
    public function preprocessRoomBookings(int $companyId, int $days = 90): array
    {
        $bookings = BookingRoom::where('company_id', $companyId)
            ->where('created_at', '>=', now()->subDays($days))
            ->with(['room', 'user', 'department'])
            ->get();

        if ($bookings->isEmpty()) {
            return $this->getEmptyRoomBookingFeatures();
        }

        $features = [
            'hourly_distribution' => $this->extractHourlyDistribution($bookings, 'start_time'),
            'daily_distribution' => $this->extractDailyDistribution($bookings),
            'weekly_trend' => $this->extractWeeklyTrend($bookings),
           
            'avg_duration' => $bookings->avg('duration_hours') ?? 0,
            'peak_hours' => $this->identifyPeakHours($bookings, 'start_time'),
            'booking_frequency' => $bookings->count() / max($days, 1),
            
            'status_distribution' => $this->extractStatusDistribution($bookings),
            'approval_rate' => $this->calculateApprovalRate($bookings),
            'cancellation_rate' => $this->calculateCancellationRate($bookings),
            
            'room_popularity' => $this->extractRoomPopularity($bookings),
            'department_usage' => $this->extractDepartmentUsage($bookings),
            
            'repeat_users' => $this->identifyRepeatUsers($bookings),
            'booking_lead_time' => $this->calculateAverageLeadTime($bookings),
            
            'unusual_patterns' => $this->detectUnusualPatterns($bookings),
        ];

        return $features;
    }

    public function preprocessVehicleBookings(int $companyId, int $days = 90): array
    {
        $bookings = VehicleBooking::where('company_id', $companyId)
            ->where('created_at', '>=', now()->subDays($days))
            ->with(['vehicle', 'user', 'department'])
            ->get();

        if ($bookings->isEmpty()) {
            return $this->getEmptyVehicleBookingFeatures();
        }

        $features = [
            'hourly_distribution' => $this->extractHourlyDistribution($bookings, 'departure_time'),
            'daily_distribution' => $this->extractDailyDistribution($bookings),
            'weekly_trend' => $this->extractWeeklyTrend($bookings),
            
            'avg_duration' => $bookings->avg('duration_hours') ?? 0,
            'destination_frequency' => $this->extractDestinationFrequency($bookings),
            'booking_frequency' => $bookings->count() / max($days, 1),
            
            'status_distribution' => $this->extractStatusDistribution($bookings),
            'completion_rate' => $this->calculateCompletionRate($bookings),
            
            'vehicle_popularity' => $this->extractVehiclePopularity($bookings),
            'department_usage' => $this->extractDepartmentUsage($bookings),
            
            'repeat_users' => $this->identifyRepeatUsers($bookings),
            'booking_lead_time' => $this->calculateAverageLeadTime($bookings),
        ];

        return $features;
    }

    public function preprocessGuestbookData(int $companyId, int $days = 90): array
    {
        $visitors = Guestbook::where('company_id', $companyId)
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        if ($visitors->isEmpty()) {
            return $this->getEmptyGuestbookFeatures();
        }

        $features = [
            'hourly_distribution' => $this->extractHourlyDistribution($visitors, 'created_at'),
            'daily_distribution' => $this->extractDailyDistribution($visitors),
            'weekly_trend' => $this->extractWeeklyTrend($visitors),
            
            'avg_visit_duration' => $this->calculateAverageVisitDuration($visitors),
            'visitor_frequency' => $visitors->count() / max($days, 1),
            'peak_hours' => $this->identifyPeakHours($visitors, 'created_at'),
            
            'institution_distribution' => $this->extractInstitutionDistribution($visitors),
            'repeat_institutions' => $this->identifyRepeatInstitutions($visitors),
            
            'purpose_categories' => $this->categorizePurposes($visitors),
        ];

        return $features;
    }

    public function preprocessDeliveryData(int $companyId, int $days = 90): array
    {
        $deliveries = Delivery::where('company_id', $companyId)
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        if ($deliveries->isEmpty()) {
            return $this->getEmptyDeliveryFeatures();
        }

        $features = [
            'hourly_distribution' => $this->extractHourlyDistribution($deliveries, 'created_at'),
            'daily_distribution' => $this->extractDailyDistribution($deliveries),
            'weekly_trend' => $this->extractWeeklyTrend($deliveries),
            
            'delivery_frequency' => $deliveries->count() / max($days, 1),
            'type_distribution' => $this->extractTypeDistribution($deliveries),
            'direction_distribution' => $this->extractDirectionDistribution($deliveries),
            
            'status_distribution' => $this->extractStatusDistribution($deliveries),
            'completion_rate' => $this->calculateCompletionRate($deliveries),
            'avg_processing_time' => $this->calculateAverageProcessingTime($deliveries),
            
            'storage_utilization' => $this->extractStorageUtilization($deliveries),
        ];

        return $features;
    }

    public function createTimeSeriesDataset(string $model, int $companyId, int $days = 0): array
    {
        $modelClass = match($model) {
            'room_booking'    => BookingRoom::class,
            'vehicle_booking' => VehicleBooking::class,
            'guestbook'       => Guestbook::class,
            'delivery'        => Delivery::class,
            default           => throw new \InvalidArgumentException("Unknown model: $model"),
        };

        $query = \Illuminate\Support\Facades\DB::table((new $modelClass)->getTable())
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');

        if ($days > 0) {
            $query->where('created_at', '>=', now()->subDays($days));
        } else {
            $historyDays = (int) AISettings::get('history_days', 730);
            $query->where('created_at', '>=', now()->subDays($historyDays));
        }

        $data = $query
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        if ($data->isEmpty()) {
            return [];
        }

        $windowDays = $days > 0 ? $days : (int) AISettings::get('history_days', 730);
        $startDate  = now()->subDays($windowDays)->startOfDay();
        $endDate    = Carbon::today();
        $totalDays  = $startDate->diffInDays($endDate) + 1;
        $countByDate = $data->pluck('count', 'date')->toArray();
        $timeSeries = [];

        for ($i = 0; $i < $totalDays; $i++) {
            $date  = $startDate->copy()->addDays($i)->format('Y-m-d');
            $count = (int) ($countByDate[$date] ?? 0);

            $timeSeries[] = [
                'date'        => $date,
                'count'       => $count,
                'day_of_week' => Carbon::parse($date)->dayOfWeek,
                'is_weekend'  => Carbon::parse($date)->isWeekend(),
                'week_of_year'=> Carbon::parse($date)->weekOfYear,
                'month'       => Carbon::parse($date)->month,
            ];
        }

        return $timeSeries;
    }

    private function extractHourlyDistribution($collection, string $timeField): array
    {
        $distribution = array_fill(0, 24, 0);
        
        foreach ($collection as $item) {
            if ($item->$timeField) {
                $hour = Carbon::parse($item->$timeField)->hour;
                $distribution[$hour]++;
            }
        }
        
        return $distribution;
    }

    private function extractDailyDistribution($collection): array
    {
        $distribution = array_fill(0, 7, 0); 
        
        foreach ($collection as $item) {
            $dayOfWeek = Carbon::parse($item->created_at)->dayOfWeek;
            $distribution[$dayOfWeek]++;
        }
        
        return $distribution;
    }

    private function extractWeeklyTrend($collection): array
    {
        return $collection->groupBy(function($item) {
            return Carbon::parse($item->created_at)->weekOfYear;
        })->map(fn($week) => $week->count())->toArray();
    }

    private function extractStatusDistribution($collection): array
    {
        return $collection->groupBy('status')
            ->map(fn($group) => $group->count())
            ->toArray();
    }

    private function calculateApprovalRate($collection): float
    {
        $total = $collection->count();
        if ($total === 0) return 0;
        
        $approved = $collection->where('status', 'approved')->count();
        return round(($approved / $total) * 100, 2);
    }

    private function calculateCancellationRate($collection): float
    {
        $total = $collection->count();
        if ($total === 0) return 0;
        
        $cancelled = $collection->whereIn('status', ['cancelled', 'rejected'])->count();
        return round(($cancelled / $total) * 100, 2);
    }

    private function calculateCompletionRate($collection): float
    {
        $total = $collection->count();
        if ($total === 0) return 0;
        
        $completed = $collection->where('status', 'completed')->count();
        return round(($completed / $total) * 100, 2);
    }

    private function extractRoomPopularity($collection): array
    {
        return $collection->groupBy('room_id')
            ->map(fn($group) => $group->count())
            ->sortDesc()
            ->take(10)
            ->toArray();
    }

    private function extractVehiclePopularity($collection): array
    {
        return $collection->groupBy('vehicle_id')
            ->map(fn($group) => $group->count())
            ->sortDesc()
            ->take(10)
            ->toArray();
    }

    private function extractDepartmentUsage($collection): array
    {
        return $collection->groupBy('department_id')
            ->map(fn($group) => $group->count())
            ->toArray();
    }

    private function identifyRepeatUsers($collection): array
    {
        $userCounts = $collection->groupBy('user_id')
            ->map(fn($group) => $group->count())
            ->filter(fn($count) => $count > 1);
        
        return [
            'count' => $userCounts->count(),
            'percentage' => $collection->count() > 0 
                ? round(($userCounts->count() / $collection->unique('user_id')->count()) * 100, 2)
                : 0,
        ];
    }

    private function calculateAverageLeadTime($collection): float
    {
        $leadTimes = [];
        
        foreach ($collection as $item) {
            $startField = $item->start_time ?? $item->departure_time ?? null;
            if ($startField) {
                $leadTime = Carbon::parse($item->created_at)
                    ->diffInHours(Carbon::parse($startField));
                if ($leadTime >= 0) {
                    $leadTimes[] = $leadTime;
                }
            }
        }
        
        return count($leadTimes) > 0 ? round(array_sum($leadTimes) / count($leadTimes), 2) : 0;
    }

    private function identifyPeakHours($collection, string $timeField): array
    {
        $hourly = $this->extractHourlyDistribution($collection, $timeField);
        arsort($hourly);
        
        return array_slice(array_keys($hourly), 0, 3, true);
    }

    private function detectUnusualPatterns($collection): array
    {
        $patterns = [];
        $lateNight = $collection->filter(function($item) {
            $hour = Carbon::parse($item->start_time ?? $item->created_at)->hour;
            return $hour >= 22 || $hour < 6;
        })->count();
        
        if ($lateNight > 0) {
            $patterns[] = [
                'type' => 'late_night_activity',
                'count' => $lateNight,
                'severity' => $lateNight > 10 ? 'high' : 'medium',
            ];
        }
        
        $weekend = $collection->filter(function($item) {
            return Carbon::parse($item->created_at)->isWeekend();
        })->count();
        
        if ($weekend > $collection->count() * 0.3) {
            $patterns[] = [
                'type' => 'high_weekend_activity',
                'count' => $weekend,
                'severity' => 'medium',
            ];
        }
        
        return $patterns;
    }

    private function extractDestinationFrequency($collection): array
    {
        return $collection->groupBy('destination')
            ->map(fn($group) => $group->count())
            ->sortDesc()
            ->take(10)
            ->toArray();
    }

    private function calculateAverageVisitDuration($collection): float
    {
        $durations = [];
        
        foreach ($collection as $visitor) {
            if ($visitor->jam_in && $visitor->jam_out) {
                try {
                    $duration = Carbon::parse($visitor->jam_in)
                        ->diffInMinutes(Carbon::parse($visitor->jam_out));
                    if ($duration > 0 && $duration < 1440) { // Less than 24 hours
                        $durations[] = $duration;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        return count($durations) > 0 ? round(array_sum($durations) / count($durations), 2) : 0;
    }

    private function extractInstitutionDistribution($collection): array
    {
        return $collection->groupBy('instansi')
            ->map(fn($group) => $group->count())
            ->sortDesc()
            ->take(10)
            ->toArray();
    }

    private function identifyRepeatInstitutions($collection): array
    {
        $institutionCounts = $collection->groupBy('instansi')
            ->map(fn($group) => $group->count())
            ->filter(fn($count) => $count > 1);
        
        return [
            'count' => $institutionCounts->count(),
            'top_repeat' => $institutionCounts->sortDesc()->take(5)->toArray(),
        ];
    }

    private function categorizePurposes($collection): array
    {
        $categories = [
            'meeting' => ['meeting', 'rapat', 'diskusi', 'pertemuan'],
            'business' => ['bisnis', 'business', 'kerjasama', 'partnership'],
            'consultation' => ['konsultasi', 'consultation', 'advice'],
            'internship' => ['magang', 'internship'],
            'visit' => ['company visit', 'visitasi', 'observasi'],
            'delivery' => ['pengiriman', 'delivery', 'kirim'],
            'other' => [],
        ];
        
        $distribution = array_fill_keys(array_keys($categories), 0);
        
        foreach ($collection as $visitor) {
            $purpose = strtolower($visitor->keperluan ?? '');
            $categorized = false;
            
            foreach ($categories as $category => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($purpose, $keyword)) {
                        $distribution[$category]++;
                        $categorized = true;
                        break 2;
                    }
                }
            }
            
            if (!$categorized) {
                $distribution['other']++;
            }
        }
        
        return $distribution;
    }

    private function extractTypeDistribution($collection): array
    {
        return $collection->groupBy('type')
            ->map(fn($group) => $group->count())
            ->toArray();
    }

    private function extractDirectionDistribution($collection): array
    {
        return $collection->groupBy('direction')
            ->map(fn($group) => $group->count())
            ->toArray();
    }

    private function calculateAverageProcessingTime($collection): float
    {
        $times = [];
        
        foreach ($collection as $delivery) {
            if ($delivery->created_at && $delivery->updated_at && $delivery->status === 'completed') {
                $time = Carbon::parse($delivery->created_at)
                    ->diffInHours(Carbon::parse($delivery->updated_at));
                if ($time >= 0) {
                    $times[] = $time;
                }
            }
        }
        
        return count($times) > 0 ? round(array_sum($times) / count($times), 2) : 0;
    }

    private function extractStorageUtilization($collection): array
    {
        return $collection->groupBy('storage_id')
            ->map(fn($group) => $group->count())
            ->toArray();
    }

    private function getEmptyRoomBookingFeatures(): array
    {
        return [
            'hourly_distribution' => array_fill(0, 24, 0),
            'daily_distribution' => array_fill(0, 7, 0),
            'weekly_trend' => [],
            'avg_duration' => 0,
            'peak_hours' => [],
            'booking_frequency' => 0,
            'status_distribution' => [],
            'approval_rate' => 0,
            'cancellation_rate' => 0,
            'room_popularity' => [],
            'department_usage' => [],
            'repeat_users' => ['count' => 0, 'percentage' => 0],
            'booking_lead_time' => 0,
            'unusual_patterns' => [],
        ];
    }

    private function getEmptyVehicleBookingFeatures(): array
    {
        return [
            'hourly_distribution' => array_fill(0, 24, 0),
            'daily_distribution' => array_fill(0, 7, 0),
            'weekly_trend' => [],
            'avg_duration' => 0,
            'destination_frequency' => [],
            'booking_frequency' => 0,
            'status_distribution' => [],
            'completion_rate' => 0,
            'vehicle_popularity' => [],
            'department_usage' => [],
            'repeat_users' => ['count' => 0, 'percentage' => 0],
            'booking_lead_time' => 0,
        ];
    }

    private function getEmptyGuestbookFeatures(): array
    {
        return [
            'hourly_distribution' => array_fill(0, 24, 0),
            'daily_distribution' => array_fill(0, 7, 0),
            'weekly_trend' => [],
            'avg_visit_duration' => 0,
            'visitor_frequency' => 0,
            'peak_hours' => [],
            'institution_distribution' => [],
            'repeat_institutions' => ['count' => 0, 'top_repeat' => []],
            'purpose_categories' => [],
        ];
    }

    private function getEmptyDeliveryFeatures(): array
    {
        return [
            'hourly_distribution' => array_fill(0, 24, 0),
            'daily_distribution' => array_fill(0, 7, 0),
            'weekly_trend' => [],
            'delivery_frequency' => 0,
            'type_distribution' => [],
            'direction_distribution' => [],
            'status_distribution' => [],
            'completion_rate' => 0,
            'avg_processing_time' => 0,
            'storage_utilization' => [],
        ];
    }
}
