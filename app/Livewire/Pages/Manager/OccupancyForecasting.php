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

    // ── Request-scoped memoization ──────────────────────────────────────────────────
    private ?array $_roomTimeSeriesCache = null;
    private ?array $_vehicleTimeSeriesCache = null;
    private ?array $_csvInfoCache = null;
    private ?array $_roomForecastCache = null;
    private ?array $_vehicleForecastCache = null;
    private ?bool $_lstmAvailableCache = null;

    public function setForecastType(string $type): void
    {
        $this->forecastType = $type;
        // Clear forecast caches when type changes
        $this->_roomForecastCache = null;
        $this->_vehicleForecastCache = null;
    }

    public function setForecastDays(int $days): void
    {
        $this->forecastDays = $days;
        
        // Auto-set date range based on preset
        $start = now()->addDay();
        $end = now()->addDays($days);
        
        $this->forecastStartDate = $start->format('Y-m-d');
        $this->forecastEndDate = $end->format('Y-m-d');
        
        // Clear forecast caches when days change
        $this->_roomForecastCache = null;
        $this->_vehicleForecastCache = null;
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
        $this->_roomForecastCache = null;
        $this->_vehicleForecastCache = null;
    }
    
    public function updatedForecastEndDate(): void
    {
        $this->recalculateForecastDays();
        $this->_roomForecastCache = null;
        $this->_vehicleForecastCache = null;
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
        
        // Clear time series and forecast caches when source changes
        $this->_roomTimeSeriesCache = null;
        $this->_vehicleTimeSeriesCache = null;
        $this->_roomForecastCache = null;
        $this->_vehicleForecastCache = null;
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
        try {
            // Use request-scoped memoization for all expensive operations
            $csvInfo = $this->getCsvInfo();
            
            // Get historical data (memoized per type)
            $roomHistory    = $this->getRoomTimeSeries();
            $vehicleHistory = $this->getVehicleTimeSeries();

            // ── DIAGNOSTIC CHECKPOINT 1: Historical Data ────────────────────────────────
            Log::info('OccupancyForecasting DIAGNOSTIC 1: Historical Data', [
                'forecastType' => $this->forecastType,
                'forecastDays' => $this->forecastDays,
                'trainingSource' => $this->trainingSource,
                'roomHistory_count' => count($roomHistory),
                'vehicleHistory_count' => count($vehicleHistory),
                'roomHistory_first' => $roomHistory[0] ?? null,
                'roomHistory_last' => !empty($roomHistory) ? end($roomHistory) : null,
                'vehicleHistory_first' => $vehicleHistory[0] ?? null,
                'vehicleHistory_last' => !empty($vehicleHistory) ? end($vehicleHistory) : null,
            ]);

            // Check LSTM availability (memoized)
            $isAvailable = $this->isLSTMAvailable();

            Log::info('OccupancyForecasting DIAGNOSTIC 2: LSTM Availability', [
                'isLSTMAvailable' => $isAvailable,
            ]);

            // Get forecasts (memoized per type)
            $roomForecast    = $this->getRoomForecast($isAvailable, $roomHistory);
            $vehicleForecast = $this->getVehicleForecast($isAvailable, $vehicleHistory);

            // ── DIAGNOSTIC CHECKPOINT 3: Forecast Data ──────────────────────────────────
            Log::info('OccupancyForecasting DIAGNOSTIC 3: Forecast Data', [
                'roomForecast_is_null' => $roomForecast === null,
                'vehicleForecast_is_null' => $vehicleForecast === null,
                'roomForecast_count' => is_array($roomForecast) ? count($roomForecast) : null,
                'vehicleForecast_count' => is_array($vehicleForecast) ? count($vehicleForecast) : null,
                'roomForecast_first' => is_array($roomForecast) && !empty($roomForecast) ? $roomForecast[0] : null,
                'vehicleForecast_first' => is_array($vehicleForecast) && !empty($vehicleForecast) ? $vehicleForecast[0] : null,
            ]);

            // ── Chart data ──────────────────────────────────────────────────────────────
            $chartData = $this->buildChartData($roomForecast, $vehicleForecast);

            // ── Occupancy stats ─────────────────────────────────────────────────────────
            $stats = $this->buildStats($roomHistory, $vehicleHistory, $roomForecast, $vehicleForecast);

            // ── DIAGNOSTIC CHECKPOINT 4: Built Data ─────────────────────────────────────
            Log::info('OccupancyForecasting DIAGNOSTIC 4: Built Data', [
                'chartData_labels_count' => count($chartData['labels'] ?? []),
                'chartData_roomData_count' => count($chartData['roomData'] ?? []),
                'chartData_vehicleData_count' => count($chartData['vehicleData'] ?? []),
                'stats_avg_room_fc' => $stats['avg_room_fc'] ?? null,
                'stats_avg_vehicle_fc' => $stats['avg_vehicle_fc'] ?? null,
                'stats_peak_day' => $stats['peak_day'] ?? null,
            ]);

            // ── Model metrics ───────────────────────────────────────────────────────────
            $lstmClient = new LSTMClient();

            Log::info('OccupancyForecasting DIAGNOSTIC 5: NORMAL RETURN PATH');

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
                'csvInfo'         => $csvInfo,
                'uploadedCsvName' => $this->uploadedCsvName,
                'uploadError'     => $this->uploadError,
                'uploadSuccess'   => $this->uploadSuccess,
                'modelMetrics'    => $lstmClient->getModelMetrics(),
            ]);
        } catch (\Exception $e) {
            Log::error('OccupancyForecasting DIAGNOSTIC: EXCEPTION CAUGHT', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return view('livewire.pages.manager.occupancy-forecasting', [
                'isLSTMAvailable' => false,
                'roomForecast'    => null,
                'vehicleForecast' => null,
                'roomHistory'     => [],
                'vehicleHistory'  => [],
                'chartData'       => ['labels' => [], 'roomData' => [], 'vehicleData' => []],
                'stats'           => $this->getEmptyStats(),
                'weather'         => null,
                'weatherInsight'  => null,
                'csvInfo'         => ['rows' => 0, 'start' => null, 'end' => null],
                'uploadedCsvName' => $this->uploadedCsvName,
                'uploadError'     => $this->uploadError,
                'uploadSuccess'   => $this->uploadSuccess,
                'modelMetrics'    => null,
            ]);
        }
    }

    // ── Request-scoped memoized methods ─────────────────────────────────────────────

    /**
     * Check if LSTM is available (request-scoped cache)
     */
    private function isLSTMAvailable(): bool
    {
        if ($this->_lstmAvailableCache !== null) {
            return $this->_lstmAvailableCache;
        }

        $lstm = new LSTMClient();
        $this->_lstmAvailableCache = $lstm->isAvailable();
        
        return $this->_lstmAvailableCache;
    }

    /**
     * Get CSV info (request-scoped cache)
     */
    private function getCsvInfo(): array
    {
        if ($this->_csvInfoCache !== null) {
            return $this->_csvInfoCache;
        }

        $reader = new CsvDataReader();
        $this->_csvInfoCache = $reader->serverCsvInfo();
        
        return $this->_csvInfoCache;
    }

    /**
     * Get room time series (request-scoped cache)
     */
    private function getRoomTimeSeries(): array
    {
        if ($this->_roomTimeSeriesCache !== null) {
            return $this->_roomTimeSeriesCache;
        }

        $this->_roomTimeSeriesCache = $this->buildTimeSeries('room');
        
        return $this->_roomTimeSeriesCache;
    }

    /**
     * Get vehicle time series (request-scoped cache)
     */
    private function getVehicleTimeSeries(): array
    {
        if ($this->_vehicleTimeSeriesCache !== null) {
            return $this->_vehicleTimeSeriesCache;
        }

        $this->_vehicleTimeSeriesCache = $this->buildTimeSeries('vehicle');
        
        return $this->_vehicleTimeSeriesCache;
    }

    /**
     * Get room forecast (request-scoped cache)
     */
    private function getRoomForecast(bool $isAvailable, array $roomHistory): ?array
    {
        if (!in_array($this->forecastType, ['room', 'combined'])) {
            Log::info('OccupancyForecasting: getRoomForecast skipped (forecastType not room/combined)', [
                'forecastType' => $this->forecastType,
            ]);
            return null;
        }

        if ($this->_roomForecastCache !== null) {
            Log::info('OccupancyForecasting: getRoomForecast using cached value');
            return $this->_roomForecastCache;
        }

        if ($isAvailable) {
            $lstm = new LSTMClient();
            Log::info('OccupancyForecasting: Calling LSTM predict for room', [
                'roomHistory_count' => count($roomHistory),
                'forecastDays' => $this->forecastDays,
            ]);
            
            $result = $lstm->predict($roomHistory, $this->forecastDays, false);
            
            Log::info('OccupancyForecasting: LSTM predict result for room', [
                'result_is_null' => $result === null,
                'result_keys' => $result ? array_keys($result) : null,
                'predictions_exists' => isset($result['predictions']),
                'predictions_type' => isset($result['predictions']) ? gettype($result['predictions']) : null,
                'predictions_count' => isset($result['predictions']) && is_array($result['predictions']) ? count($result['predictions']) : null,
                'first_prediction' => isset($result['predictions'][0]) ? $result['predictions'][0] : null,
            ]);
            
            $lstmPredictions = $result['predictions'] ?? null;

            // If LSTM was reachable but returned null/empty predictions (e.g. insufficient
            // data, model error, timeout on the /predict call), fall back to moving average
            // so the summary cards always show a value rather than —.
            if (empty($lstmPredictions)) {
                Log::warning('OccupancyForecasting: LSTM available but returned no room predictions — using moving average fallback');
                $this->_roomForecastCache = $this->movingAverageForecast($roomHistory, $this->forecastDays);
            } else {
                $this->_roomForecastCache = $lstmPredictions;
            }
        } else {
            Log::info('OccupancyForecasting: Using fallback moving average for room');
            // Fallback: simple moving-average projection
            $this->_roomForecastCache = $this->movingAverageForecast($roomHistory, $this->forecastDays);
        }

        Log::info('OccupancyForecasting: getRoomForecast final result', [
            'cache_is_null' => $this->_roomForecastCache === null,
            'cache_count' => is_array($this->_roomForecastCache) ? count($this->_roomForecastCache) : null,
        ]);

        return $this->_roomForecastCache;
    }

    /**
     * Get vehicle forecast (request-scoped cache)
     */
    private function getVehicleForecast(bool $isAvailable, array $vehicleHistory): ?array
    {
        if (!in_array($this->forecastType, ['vehicle', 'combined'])) {
            Log::info('OccupancyForecasting: getVehicleForecast skipped (forecastType not vehicle/combined)', [
                'forecastType' => $this->forecastType,
            ]);
            return null;
        }

        if ($this->_vehicleForecastCache !== null) {
            Log::info('OccupancyForecasting: getVehicleForecast using cached value');
            return $this->_vehicleForecastCache;
        }

        if ($isAvailable) {
            $lstm = new LSTMClient();
            Log::info('OccupancyForecasting: Calling LSTM predict for vehicle', [
                'vehicleHistory_count' => count($vehicleHistory),
                'forecastDays' => $this->forecastDays,
            ]);
            
            $result = $lstm->predict($vehicleHistory, $this->forecastDays, false);
            
            Log::info('OccupancyForecasting: LSTM predict result for vehicle', [
                'result_is_null' => $result === null,
                'result_keys' => $result ? array_keys($result) : null,
                'predictions_exists' => isset($result['predictions']),
                'predictions_type' => isset($result['predictions']) ? gettype($result['predictions']) : null,
                'predictions_count' => isset($result['predictions']) && is_array($result['predictions']) ? count($result['predictions']) : null,
                'first_prediction' => isset($result['predictions'][0]) ? $result['predictions'][0] : null,
            ]);
            
            $lstmVehiclePredictions = $result['predictions'] ?? null;

            // If LSTM was reachable but returned null/empty predictions, fall back to
            // moving average so the vehicle card always shows a value rather than —.
            if (empty($lstmVehiclePredictions)) {
                Log::warning('OccupancyForecasting: LSTM available but returned no vehicle predictions — using moving average fallback');
                $this->_vehicleForecastCache = $this->movingAverageForecast($vehicleHistory, $this->forecastDays);
            } else {
                $this->_vehicleForecastCache = $lstmVehiclePredictions;
            }
        } else {
            Log::info('OccupancyForecasting: Using fallback moving average for vehicle');
            // Fallback: simple moving-average projection
            $this->_vehicleForecastCache = $this->movingAverageForecast($vehicleHistory, $this->forecastDays);
        }

        Log::info('OccupancyForecasting: getVehicleForecast final result', [
            'cache_is_null' => $this->_vehicleForecastCache === null,
            'cache_count' => is_array($this->_vehicleForecastCache) ? count($this->_vehicleForecastCache) : null,
        ]);

        return $this->_vehicleForecastCache;
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
     * Read room history from server CSV (combines offline + online columns in a single cached pass)
     */
    private function readRoomHistoryFromServerCsv(): array
    {
        $reader = new CsvDataReader();
        try {
            return $reader->readServerCsvColumnsSummed(['offline_room_bookings', 'online_room_bookings']);
        } catch (\Throwable $e) {
            Log::warning('OccupancyForecasting: failed to read room history from server CSV', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Read room history from uploaded CSV (combines offline + online columns in a single cached pass)
     */
    private function readRoomHistoryFromCsv(string $storagePath): array
    {
        $reader = new CsvDataReader();
        try {
            return $reader->readUploadedCsvColumnsSummed($storagePath, ['offline_room_bookings', 'online_room_bookings']);
        } catch (\Throwable $e) {
            Log::warning('OccupancyForecasting: failed to read room history from uploaded CSV', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    // ── Private helpers ─────────────────────────────────────────────────────────────
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
        $dowTotals = array_fill(0, 7, 0.0);   // Sun=0 … Sat=6
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
        Log::info('OccupancyForecasting: buildChartData called', [
            'room_is_null' => $room === null,
            'vehicle_is_null' => $vehicle === null,
            'room_count' => is_array($room) ? count($room) : null,
            'vehicle_count' => is_array($vehicle) ? count($vehicle) : null,
        ]);

        $labels = [];
        $roomData    = [];
        $vehicleData = [];

        // Use whichever forecast is available for labels
        $base = $room ?? $vehicle ?? [];
        
        Log::info('OccupancyForecasting: buildChartData base selected', [
            'base_count' => count($base),
            'base_first' => $base[0] ?? null,
        ]);
        
        foreach ($base as $p) {
            $labels[]    = date('d/m', strtotime($p['date']));
            $roomData[]  = $room    ? round($p['predicted'], 1) : null;
        }

        if ($vehicle) {
            foreach ($vehicle as $p) {
                $vehicleData[] = round($p['predicted'], 1);
            }
        }

        $result = compact('labels', 'roomData', 'vehicleData');
        
        Log::info('OccupancyForecasting: buildChartData result', [
            'labels_count' => count($result['labels']),
            'roomData_count' => count($result['roomData']),
            'vehicleData_count' => count($result['vehicleData']),
        ]);

        return $result;
    }

    private function buildStats(array $roomHist, array $vehicleHist, ?array $roomFc, ?array $vehicleFc): array
    {
        Log::info('OccupancyForecasting: buildStats called', [
            'roomHist_count' => count($roomHist),
            'vehicleHist_count' => count($vehicleHist),
            'roomFc_is_null' => $roomFc === null,
            'vehicleFc_is_null' => $vehicleFc === null,
            'roomFc_count' => is_array($roomFc) ? count($roomFc) : null,
            'vehicleFc_count' => is_array($vehicleFc) ? count($vehicleFc) : null,
        ]);

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

        $result = [
            'avg_room_hist'    => round($avgRoomHist, 1),
            'avg_vehicle_hist' => round($avgVehicleHist, 1),
            'avg_room_fc'      => $avgRoomFc    ? round($avgRoomFc, 1)    : '—',
            'avg_vehicle_fc'   => $avgVehicleFc ? round($avgVehicleFc, 1) : '—',
            'room_trend'       => $roomTrend,
            'vehicle_trend'    => $vehicleTrend,
            'peak_day'         => $peakDay ?? '—',
            'total_room_fc'    => $roomFc    ? round(array_sum(array_column($roomFc, 'predicted')))    : '—',
            'total_vehicle_fc' => $vehicleFc ? round(array_sum(array_column($vehicleFc, 'predicted'))) : '—',
        ];

        Log::info('OccupancyForecasting: buildStats result', [
            'avg_room_fc' => $result['avg_room_fc'],
            'avg_vehicle_fc' => $result['avg_vehicle_fc'],
            'peak_day' => $result['peak_day'],
        ]);

        return $result;
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
                            'icon'    => '🌧️',
                            'message' => "Rain likely ({$rain}% chance) on {$day['date_label']} — expect lower walk-in occupancy (~{$predicted} bookings).",
                            'type'    => 'warning',
                        ];
                    } elseif ($rain <= 20 && in_array($day['summary']['weather'] ?? 99, [0, 1, 2])) {
                        $insights[] = [
                            'date'    => $day['date_label'],
                            'icon'    => '☀️',
                            'message' => "Clear weather on {$day['date_label']} — good conditions for higher visitor turnout (~{$predicted} bookings).",
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

    private function getEmptyStats(): array
    {
        return [
            'avg_room_hist'    => 0,
            'avg_vehicle_hist' => 0,
            'avg_room_fc'      => '—',
            'avg_vehicle_fc'   => '—',
            'room_trend'       => 0,
            'vehicle_trend'    => 0,
            'peak_day'         => '—',
            'total_room_fc'    => '—',
            'total_vehicle_fc' => '—',
        ];
    }
}
