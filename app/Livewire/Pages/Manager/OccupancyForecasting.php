<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\BookingRoom;
use App\Models\VehicleBooking;
use App\Models\Guestbook;
use App\Models\AISettings;
use App\Services\AI\LSTMClient;
use App\Services\AI\CsvDataReader;
use App\Services\AI\DataPreprocessor;
use App\Services\WeatherService;
use Carbon\Carbon;

#[Layout('layouts.manager')]
#[Title('Occupancy Forecasting')]
class OccupancyForecasting extends Component
{
    use WithFileUploads;
    public string $forecastType     = 'room';   // choice of room | vehicle | combined
    public int    $forecastDays     = 21;      // default to 21 days
    public ?string $forecastStartDate = null;
    public ?string $forecastEndDate = null;
    public string $trainingSource   = 'csv_server'; // csv_server | csv_upload | live_db
    public        $uploadedCsv      = null;
    public ?string $uploadedCsvPath = null;   // storage-relative path for uploaded CSV
    public ?string $uploadedCsvName = null;
    public ?string $uploadError     = null;
    public ?string $uploadSuccess   = null;
    public ?array  $csvInfo         = null;
    // public bool   $withWeather   = true;

    public function setForecastType(string $type): void
    {
        $this->forecastType = $type;
    }

    public function setForecastDays(int $days): void
    {
        $this->forecastDays = $days;
        
        // Auto-set date range based on preset
        $start = now()->addDay();
        $end = now()->addDays($days);
        
        $this->forecastStartDate = $start->format('Y-m-d');
        $this->forecastEndDate = $end->format('Y-m-d');
    }
    
    public function mount(): void
    {
        // Initialize default date range if not set
        if (!$this->forecastStartDate || !$this->forecastEndDate) {
            $this->setForecastDays($this->forecastDays);
        }
    }
    
    public function updatedForecastStartDate(): void
    {
        $this->recalculateForecastDays();
    }
    
    public function updatedForecastEndDate(): void
    {
        $this->recalculateForecastDays();
    }
    
    private function recalculateForecastDays(): void
    {
        if ($this->forecastStartDate && $this->forecastEndDate) {
            $start = Carbon::parse($this->forecastStartDate);
            $end = Carbon::parse($this->forecastEndDate);
            
            // Ensure End >= Start
            if ($end >= $start) {
                $this->forecastDays = $end->diffInDays($start) + 1;
            }
        }
    }

    public function setTrainingSource(string $source): void
    {
        $this->trainingSource = $source;
        $this->uploadError   = null;
        $this->uploadSuccess = null;
    }

    public function uploadCsv(): void
    {
        $this->uploadError   = null;
        $this->uploadSuccess = null;

        $this->validate(['uploadedCsv' => 'required|file|mimes:csv,txt|max:10240']);

        try {
            $reader  = new CsvDataReader();
            $tmpPath = $this->uploadedCsv->store(CsvDataReader::UPLOAD_PATH, CsvDataReader::DISK);
            $missing = $reader->validateColumns($tmpPath);

            if (!empty($missing)) {
                Storage::disk(CsvDataReader::DISK)->delete($tmpPath);
                $this->uploadError = __('app.csv_missing_columns', ['columns' => implode(', ', $missing)]);
                $this->uploadedCsv = null;
                return;
            }

            // Remove old upload if one exists
            if ($this->uploadedCsvPath) {
                Storage::disk(CsvDataReader::DISK)->delete($this->uploadedCsvPath);
            }

            $this->uploadedCsvPath = $tmpPath;
            $this->uploadedCsvName = $this->uploadedCsv->getClientOriginalName();
            $this->uploadedCsv     = null;
            $this->trainingSource  = 'csv_upload';
            $this->uploadSuccess   = __('app.csv_upload_success', ['name' => $this->uploadedCsvName]);

        } catch (\Throwable $e) {
            Log::error('OccupancyForecasting: CSV upload failed', ['error' => $e->getMessage()]);
            $this->uploadError = __('app.csv_upload_failed');
            $this->uploadedCsv = null;
        }
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;
        $reader    = new CsvDataReader();

        // ΓöÇΓöÇ CSV INFO for server csv ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
        $this->csvInfo = $reader->serverCsvInfo();

        // ΓöÇΓöÇ Determine data source for training ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
        $roomHistory    = $this->buildTimeSeries('room');
        $vehicleHistory = $this->buildTimeSeries('vehicle');

        // LSTM forecast
        $lstm        = new LSTMClient();
        $isAvailable = $lstm->isAvailable();

        $roomForecast    = null;
        $vehicleForecast = null;

        if ($isAvailable) {
            if (in_array($this->forecastType, ['room', 'combined'])) {
                $result = $lstm->predict($roomHistory, $this->forecastDays, false);
                $roomForecast = $result['predictions'] ?? null;
            }
            if (in_array($this->forecastType, ['vehicle', 'combined'])) {
                $result = $lstm->predict($vehicleHistory, $this->forecastDays, false);
                $vehicleForecast = $result['predictions'] ?? null;
            }
        } else {
            // Fallback: simple moving-average projection
            if (in_array($this->forecastType, ['room', 'combined'])) {
                $roomForecast = $this->movingAverageForecast($roomHistory, $this->forecastDays);
            }
            if (in_array($this->forecastType, ['vehicle', 'combined'])) {
                $vehicleForecast = $this->movingAverageForecast($vehicleHistory, $this->forecastDays);
            }
        }

        // ΓöÇΓöÇ Chart data ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
        $chartData = $this->buildChartData($roomForecast, $vehicleForecast);

        // ΓöÇΓöÇ Occupancy stats ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
        $stats = $this->buildStats($roomHistory, $vehicleHistory, $roomForecast, $vehicleForecast);

        return view('livewire.pages.manager.occupancy-forecasting', [
            'isLSTMAvailable' => $isAvailable,
            'roomForecast'    => $roomForecast,
            'vehicleForecast' => $vehicleForecast,
            'roomHistory'     => $roomHistory,
            'vehicleHistory'  => $vehicleHistory,
            'chartData'       => $chartData,
            'stats'           => $stats,
            'weather'         => null,
            'weatherInsight'  => null,
            'csvInfo'         => $this->csvInfo,
            'uploadedCsvName' => $this->uploadedCsvName,
            'uploadError'     => $this->uploadError,
            'uploadSuccess'   => $this->uploadSuccess,
        ]);
    }

    /**
     * Build time series from the selected data source for a given booking type.
     * type: 'room' | 'vehicle'
     */
    private function buildTimeSeries(string $type): array
    {
        $reader = new CsvDataReader();

        // Column names in the CSV that correspond to each booking type
        $csvMetric = match($type) {
            'room'    => 'combined_rooms',  // we'll need a special handler
            'vehicle' => 'vehicle_bookings',
            default   => 'visitors',
        };

        switch ($this->trainingSource) {

            case 'csv_upload':
                if ($this->uploadedCsvPath) {
                    try {
                        if ($type === 'room') {
                            return $this->readRoomHistoryFromCsv($this->uploadedCsvPath);
                        }
                        return $reader->readUploadedCsv($this->uploadedCsvPath, $csvMetric);
                    } catch (\Throwable $e) {
                        Log::warning('OccupancyForecasting: uploaded CSV unreadable, falling back', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                // Fall through to server CSV if upload is missing
                $this->trainingSource = 'csv_server';
                // fallthrough

            case 'csv_server':
                if ($type === 'room') {
                    return $this->readRoomHistoryFromServerCsv();
                }
                return $reader->readServerCsv($csvMetric);

            case 'live_db':
                $companyId = Auth::user()->company_id;
                return $type === 'room'
                    ? $this->getRoomHistory($companyId)
                    : $this->getVehicleHistory($companyId);

            default:
                return [];
        }
    }

    /**
     * Read room history from server CSV (combines offline + online columns)
     */
    private function readRoomHistoryFromServerCsv(): array
    {
        $reader = new CsvDataReader();
        try {
            $offline = $reader->readServerCsv('offline_room_bookings');
            $online  = $reader->readServerCsv('online_room_bookings');

            // Merge by date
            $merged = [];
            $byDate = [];
            foreach ($offline as $row) {
                $byDate[$row['date']] = $row['count'];
            }
            foreach ($online as $row) {
                $byDate[$row['date']] = ($byDate[$row['date']] ?? 0) + $row['count'];
            }

            foreach ($byDate as $date => $count) {
                $merged[] = ['date' => $date, 'count' => $count];
            }

            usort($merged, fn($a, $b) => strcmp($a['date'], $b['date']));
            return $merged;

        } catch (\Throwable $e) {
            Log::warning('OccupancyForecasting: failed to read room history from server CSV', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Read room history from uploaded CSV (combines offline + online columns)
     */
    private function readRoomHistoryFromCsv(string $storagePath): array
    {
        $reader = new CsvDataReader();
        try {
            $offline = $reader->readUploadedCsv($storagePath, 'offline_room_bookings');
            $online  = $reader->readUploadedCsv($storagePath, 'online_room_bookings');

            $merged = [];
            $byDate = [];
            foreach ($offline as $row) {
                $byDate[$row['date']] = $row['count'];
            }
            foreach ($online as $row) {
                $byDate[$row['date']] = ($byDate[$row['date']] ?? 0) + $row['count'];
            }

            foreach ($byDate as $date => $count) {
                $merged[] = ['date' => $date, 'count' => $count];
            }

            usort($merged, fn($a, $b) => strcmp($a['date'], $b['date']));
            return $merged;

        } catch (\Throwable $e) {
            Log::warning('OccupancyForecasting: failed to read room history from uploaded CSV', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    // ΓöÇΓöÇ Private helpers ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
    private function getRoomHistory(int $companyId): array
    {
        return BookingRoom::where('company_id', $companyId)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->map(fn($r) => ['date' => $r->date, 'count' => (int) $r->count])
            ->toArray();
    }

    private function getVehicleHistory(int $companyId): array
    {
        return VehicleBooking::where('company_id', $companyId)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->map(fn($r) => ['date' => $r->date, 'count' => (int) $r->count])
            ->toArray();
    }

    private function movingAverageForecast(array $history, int $days): array
    {
        // All magic numbers read from ai_settings table
        $window     = (int)   AISettings::get('ma_window',      7);
        $lowerMult  = (float) AISettings::get('ma_lower_bound', 0.8);
        $upperMult  = (float) AISettings::get('ma_upper_bound', 1.2);
        $confidence = (float) AISettings::get('ma_confidence',  0.60);
        $floorAvg   = (float) AISettings::get('ma_floor_avg',   3.0);

        if (empty($history)) {
            $avg = $floorAvg;
        } else {
            $slice = array_slice($history, -$window);
            $avg   = array_sum(array_column($slice, 'count')) / count($slice);
        }

        // Build a day-of-week multiplier from historical data so the forecast
        // reflects real patterns (e.g. busier on Fridays) instead of random noise.
        $dowTotals = array_fill(0, 7, 0.0);   // Sun=0 ΓÇª Sat=6
        $dowCounts = array_fill(0, 7, 0);

        foreach ($history as $row) {
            $dow = (int) date('w', strtotime($row['date']));
            $dowTotals[$dow] += (float) $row['count'];
            $dowCounts[$dow]++;
        }

        // Per-DOW average; fall back to the global avg when a day has no data.
        $dowAvg = [];
        for ($d = 0; $d < 7; $d++) {
            $dowAvg[$d] = $dowCounts[$d] > 0
                ? $dowTotals[$d] / $dowCounts[$d]
                : $avg;
        }

        // Express each DOW relative to the overall mean so we get a multiplier
        // centred around 1.0.  Protect against a zero overall average.
        $overallHistAvg = $avg > 0 ? $avg : $floorAvg;
        $dowMultiplier  = [];
        for ($d = 0; $d < 7; $d++) {
            $dowMultiplier[$d] = $dowAvg[$d] / $overallHistAvg;
        }

        $lastDate = !empty($history) ? end($history)['date'] : date('Y-m-d');
        $forecast = [];

        for ($i = 1; $i <= $days; $i++) {
            $date      = date('Y-m-d', strtotime($lastDate . " +{$i} days"));
            $dow       = (int) date('w', strtotime($date));
            $predicted = round(max(0, $avg * $dowMultiplier[$dow]), 1);

            $forecast[] = [
                'date'        => $date,
                'predicted'   => $predicted,
                'lower_bound' => round(max(0, $predicted * $lowerMult), 1),
                'upper_bound' => round($predicted * $upperMult, 1),
                'confidence'  => $confidence,
            ];
        }

        return $forecast;
    }

    private function buildChartData(?array $room, ?array $vehicle): array
    {
        $labels = [];
        $roomData    = [];
        $vehicleData = [];

        // Use whichever forecast is available for labels
        $base = $room ?? $vehicle ?? [];
        foreach ($base as $p) {
            $labels[]    = date('d/m', strtotime($p['date']));
            $roomData[]  = $room    ? round($p['predicted'], 1) : null;
        }

        if ($vehicle) {
            foreach ($vehicle as $p) {
                $vehicleData[] = round($p['predicted'], 1);
            }
        }

        return compact('labels', 'roomData', 'vehicleData');
    }

    private function buildStats(array $roomHist, array $vehicleHist, ?array $roomFc, ?array $vehicleFc): array
    {
        $avgRoomHist    = $this->avg(array_column($roomHist, 'count'));
        $avgVehicleHist = $this->avg(array_column($vehicleHist, 'count'));
        $avgRoomFc      = $roomFc    ? $this->avg(array_column($roomFc, 'predicted'))    : null;
        $avgVehicleFc   = $vehicleFc ? $this->avg(array_column($vehicleFc, 'predicted')) : null;

        $roomTrend    = ($avgRoomFc && $avgRoomHist > 0)
            ? round(($avgRoomFc - $avgRoomHist) / $avgRoomHist * 100, 1) : 0;
        $vehicleTrend = ($avgVehicleFc && $avgVehicleHist > 0)
            ? round(($avgVehicleFc - $avgVehicleHist) / $avgVehicleHist * 100, 1) : 0;

        $peakDay = null;
        if ($roomFc) {
            $max = max(array_column($roomFc, 'predicted'));
            foreach ($roomFc as $p) {
                if (round($p['predicted'], 1) === round($max, 1)) {
                    $peakDay = Carbon::parse($p['date'])->isoFormat('ddd, D MMM');
                    break;
                }
            }
        }

        return [
            'avg_room_hist'    => round($avgRoomHist, 1),
            'avg_vehicle_hist' => round($avgVehicleHist, 1),
            'avg_room_fc'      => $avgRoomFc    ? round($avgRoomFc, 1)    : 'ΓÇö',
            'avg_vehicle_fc'   => $avgVehicleFc ? round($avgVehicleFc, 1) : 'ΓÇö',
            'room_trend'       => $roomTrend,
            'vehicle_trend'    => $vehicleTrend,
            'peak_day'         => $peakDay ?? 'ΓÇö',
            'total_room_fc'    => $roomFc    ? round(array_sum(array_column($roomFc, 'predicted')))    : 'ΓÇö',
            'total_vehicle_fc' => $vehicleFc ? round(array_sum(array_column($vehicleFc, 'predicted'))) : 'ΓÇö',
        ];
    }

    private function buildWeatherInsight(?array $weather, ?array $roomFc): ?array
    {
        if (!$weather || !$roomFc) return null;

        $insights = [];
        foreach ($weather['forecast'] as $day) {
            $date = $day['date'];
            foreach ($roomFc as $fc) {
                if ($fc['date'] === $date) {
                    $rain    = $day['rain_chance'];
                    $weather_desc = $day['summary']['weather_desc'] ?? '';
                    $predicted = round($fc['predicted'], 1);

                    if ($rain >= 60) {
                        $insights[] = [
                            'date'    => $day['date_label'],
                            'icon'    => '≡ƒîº∩╕Å',
                            'message' => "Rain likely ({$rain}% chance) on {$day['date_label']} ΓÇö expect lower walk-in occupancy (~{$predicted} bookings).",
                            'type'    => 'warning',
                        ];
                    } elseif ($rain <= 20 && in_array($day['summary']['weather'] ?? 99, [0, 1, 2])) {
                        $insights[] = [
                            'date'    => $day['date_label'],
                            'icon'    => 'ΓÿÇ∩╕Å',
                            'message' => "Clear weather on {$day['date_label']} ΓÇö good conditions for higher visitor turnout (~{$predicted} bookings).",
                            'type'    => 'positive',
                        ];
                    }
                    break;
                }
            }
        }

        return $insights ?: null;
    }

    private function avg(array $values): float
    {
        return count($values) > 0 ? array_sum($values) / count($values) : 0;
    }
}
