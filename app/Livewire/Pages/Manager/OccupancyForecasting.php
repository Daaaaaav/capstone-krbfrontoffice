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
use Carbon\Carbon;

#[Layout('layouts.manager')]
#[Title('Occupancy Forecasting')]
class OccupancyForecasting extends Component
{
    use WithFileUploads;
    public string $forecastType     = 'combined';   // room | vehicle | combined (default)
    public int    $forecastDays     = 21;      // 7 | 14 | 21 (default)
    public ?string $forecastStartDate = null;
    public ?string $forecastEndDate = null;
    public string $trainingSource   = 'csv_server'; // csv_server (default) | csv_upload | live_db
    public        $uploadedCsv      = null;
    public ?string $uploadedCsvPath = null;
    public ?string $uploadedCsvName = null;
    public ?string $uploadError     = null;
    public ?string $uploadSuccess   = null;
    public ?array  $csvInfo         = null;
    public bool    $isLSTMAvailable = false;
    public ?array  $modelMetrics    = null;
    public array   $roomHistory     = [];
    public array   $vehicleHistory  = [];
    public ?string $historySourceKey = null;

    private CsvDataReader $csvReader;

    public function mount(): void
    {
        $this->initializeForecastDates();
        $this->csvReader       = new CsvDataReader();
        $this->csvInfo         = $this->csvReader->serverCsvInfo();
        $lstm                  = app(LSTMClient::class);
        $this->isLSTMAvailable = $lstm->isAvailable();
        $this->modelMetrics    = $this->isLSTMAvailable ? $lstm->getModelMetrics() : null;

        // Build history once on initial mount.
        $this->rebuildHistory();
    }

    public function updated($propertyName): void
    {
        if ($propertyName === 'forecastStartDate' || $propertyName === 'forecastEndDate') {
            $this->syncForecastDaysFromDates();
        }
    }

    private function initializeForecastDates(): void
    {
        if (!$this->forecastStartDate || !$this->forecastEndDate) {
            $this->forecastStartDate = now()->addDay()->format('Y-m-d');
            $this->forecastEndDate = now()->addDays($this->forecastDays)->format('Y-m-d');
        }
    }

    private function syncForecastDaysFromDates(): void
    {
        if ($this->forecastStartDate && $this->forecastEndDate) {
            $start = \Carbon\Carbon::parse($this->forecastStartDate);
            $end = \Carbon\Carbon::parse($this->forecastEndDate);
            $this->forecastDays = max(1, $start->diffInDays($end));
        }
    }

    public function setForecastType(string $type): void
    {
        $this->forecastType = $type;
    }

    public function setForecastDays(int $days): void
    {
        $this->forecastDays = $days;
        $this->forecastStartDate = now()->addDay()->format('Y-m-d');
        $this->forecastEndDate = now()->addDays($days)->format('Y-m-d');
    }

    public function setTrainingSource(string $source): void
    {
        $this->trainingSource = $source;
        $this->uploadError   = null;
        $this->uploadSuccess = null;
        $this->rebuildHistory();
    }

    public function uploadCsv(): void
    {
        $this->uploadError   = null;
        $this->uploadSuccess = null;

        $this->validate(
            ['uploadedCsv' => 'required|file|mimes:csv,txt|max:10240'],
            [
                'uploadedCsv.required' => __('app.csv_error_no_file'),
                'uploadedCsv.file'     => __('app.csv_error_not_file'),
                'uploadedCsv.mimes'    => __('app.csv_error_wrong_type'),
                'uploadedCsv.max'      => __('app.csv_error_too_large'),
            ]
        );

        try {
            $uploadDir = Storage::disk(CsvDataReader::DISK)->path(CsvDataReader::UPLOAD_PATH);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $reader  = $this->csvReader ?? new CsvDataReader();
            $tmpPath = $this->uploadedCsv->store(CsvDataReader::UPLOAD_PATH, CsvDataReader::DISK);

            if (!$tmpPath) {
                throw new \RuntimeException('File could not be stored. Check storage permissions.');
            }

            $missing = $reader->validateColumns($tmpPath);

            if (!empty($missing)) {
                Storage::disk(CsvDataReader::DISK)->delete($tmpPath);
                $this->uploadError = __('app.csv_missing_columns', ['columns' => implode(', ', $missing)]);
                $this->uploadedCsv = null;
                return;
            }

            if ($this->uploadedCsvPath) {
                Storage::disk(CsvDataReader::DISK)->delete($this->uploadedCsvPath);
            }

            $this->uploadedCsvPath = $tmpPath;
            $this->uploadedCsvName = $this->uploadedCsv->getClientOriginalName();
            $this->uploadedCsv     = null;
            $this->trainingSource  = 'csv_upload';
            $this->uploadSuccess   = __('app.csv_upload_success', ['name' => $this->uploadedCsvName]);

            if (config('app.debug')) {
                Log::info('OccupancyForecasting: CSV uploaded', [
                    'path' => $tmpPath,
                    'name' => $this->uploadedCsvName,
                ]);
            }

            $this->rebuildHistory();

        } catch (\Throwable $e) {
            Log::error('OccupancyForecasting: CSV upload failed', ['error' => $e->getMessage()]);
            $this->uploadError = __('app.csv_upload_failed_detail', [
                'detail' => $e->getMessage(),
            ]);
            $this->uploadedCsv = null;
        }
    }

    public function render()
    {
        if ($this->getHistorySourceKey() !== $this->historySourceKey) {
            $this->rebuildHistory();
        }

        $roomHistory    = $this->roomHistory;
        $vehicleHistory = $this->vehicleHistory;

        $isAvailable  = $this->isLSTMAvailable;
        $modelMetrics = $this->modelMetrics;

        $roomForecast    = null;
        $vehicleForecast = null;

        if ($isAvailable) {
            $lstm = app(LSTMClient::class);
            if (in_array($this->forecastType, ['room', 'combined'])) {
                $result = $lstm->predict($roomHistory, $this->forecastDays, false);
                $roomForecast = $result['predictions'] ?? null;
                
                if (config('app.debug')) {
                    Log::info('OccupancyForecasting: room forecast extraction', [
                        'result_is_null'     => is_null($result),
                        'result_keys'        => is_array($result) ? array_keys($result) : 'n/a',
                        'predictions_count'  => is_array($roomForecast) ? count($roomForecast) : 'null',
                        'first_prediction'   => is_array($roomForecast) && count($roomForecast) > 0 ? $roomForecast[0] : 'empty',
                        'history_count'      => count($roomHistory),
                    ]);
                }
            }
            if (in_array($this->forecastType, ['vehicle', 'combined'])) {
                $result = $lstm->predict($vehicleHistory, $this->forecastDays, false);
                $vehicleForecast = $result['predictions'] ?? null;
                
                if (config('app.debug')) {
                    Log::info('OccupancyForecasting: vehicle forecast extraction', [
                        'result_is_null'     => is_null($result),
                        'result_keys'        => is_array($result) ? array_keys($result) : 'n/a',
                        'predictions_count'  => is_array($vehicleForecast) ? count($vehicleForecast) : 'null',
                        'first_prediction'   => is_array($vehicleForecast) && count($vehicleForecast) > 0 ? $vehicleForecast[0] : 'empty',
                        'history_count'      => count($vehicleHistory),
                    ]);
                }
            }
    
            if (($roomForecast === null || empty($roomForecast)) && in_array($this->forecastType, ['room', 'combined'])) {
                $maSettings = AISettings::getMultiple([
                    'ma_window'      => 7,
                    'ma_lower_bound' => 0.8,
                    'ma_upper_bound' => 1.2,
                    'ma_confidence'  => 0.60,
                    'ma_floor_avg'   => 3.0,
                ]);
                $roomForecast = $this->movingAverageForecast($roomHistory, $this->forecastDays, $maSettings);
                
                if (config('app.debug')) {
                    Log::info('OccupancyForecasting: falling back to MA for room forecast', [
                        'reason' => 'LSTM returned null or empty',
                        'ma_forecast_count' => count($roomForecast),
                    ]);
                }
            }
            
            if (($vehicleForecast === null || empty($vehicleForecast)) && in_array($this->forecastType, ['vehicle', 'combined'])) {
                $maSettings = AISettings::getMultiple([
                    'ma_window'      => 7,
                    'ma_lower_bound' => 0.8,
                    'ma_upper_bound' => 1.2,
                    'ma_confidence'  => 0.60,
                    'ma_floor_avg'   => 3.0,
                ]);
                $vehicleForecast = $this->movingAverageForecast($vehicleHistory, $this->forecastDays, $maSettings);
                
                if (config('app.debug')) {
                    Log::info('OccupancyForecasting: falling back to MA for vehicle forecast', [
                        'reason' => 'LSTM returned null or empty',
                        'ma_forecast_count' => count($vehicleForecast),
                    ]);
                }
            }
        } else {
            // Read MA settings once for this render cycle
            $maSettings = AISettings::getMultiple([
                'ma_window'      => 7,
                'ma_lower_bound' => 0.8,
                'ma_upper_bound' => 1.2,
                'ma_confidence'  => 0.60,
                'ma_floor_avg'   => 3.0,
            ]);
            if (in_array($this->forecastType, ['room', 'combined'])) {
                $roomForecast = $this->movingAverageForecast($roomHistory, $this->forecastDays, $maSettings);
            }
            if (in_array($this->forecastType, ['vehicle', 'combined'])) {
                $vehicleForecast = $this->movingAverageForecast($vehicleHistory, $this->forecastDays, $maSettings);
            }
        }

        $chartData = $this->buildChartData($roomForecast, $vehicleForecast);
        $stats     = $this->buildStats($roomHistory, $vehicleHistory, $roomForecast, $vehicleForecast);

        $firstRoomPred    = $roomForecast    ? ($roomForecast[0]['date'] ?? 'none')                                        : 'n/a';
        $lastRoomPred     = $roomForecast    ? ($roomForecast[count($roomForecast) - 1]['date'] ?? 'none')                  : 'n/a';
        $firstVehiclePred = $vehicleForecast ? ($vehicleForecast[0]['date'] ?? 'none')                                     : 'n/a';
        $lastVehiclePred  = $vehicleForecast ? ($vehicleForecast[count($vehicleForecast) - 1]['date'] ?? 'none')            : 'n/a';
        $firstChartLabel  = $chartData['labels'][0] ?? 'none';
        $lastChartLabel   = $chartData['labels'][count($chartData['labels']) - 1] ?? 'none';

        if (config('app.debug')) {
            Log::info('OccupancyForecasting: single source of truth audit', [
                'first_room_prediction'    => $firstRoomPred,
                'last_room_prediction'     => $lastRoomPred,
                'first_vehicle_prediction' => $firstVehiclePred,
                'last_vehicle_prediction'  => $lastVehiclePred,
                'first_chart_label'        => $firstChartLabel,
                'last_chart_label'         => $lastChartLabel,
                'chart_label_count'        => count($chartData['labels']),
                'room_forecast_count'      => $roomForecast    ? count($roomForecast)    : 0,
                'vehicle_forecast_count'   => $vehicleForecast ? count($vehicleForecast) : 0,
            ]);

            Log::info('OccupancyForecasting: metrics pipeline trace', [
                'lstm_available'       => $isAvailable,
                'model_metrics_null'   => is_null($modelMetrics),
                'metrics_available'    => $modelMetrics['available'] ?? false,
                'metrics_trained_at'   => $modelMetrics['trained_at'] ?? 'null',
                'metrics_mae'          => $modelMetrics['mae'] ?? 'null',
                'metrics_rmse'         => $modelMetrics['rmse'] ?? 'null',
                'metrics_epochs'       => $modelMetrics['epochs_run'] ?? 'null',
            ]);
        }

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
            'modelMetrics'    => $modelMetrics,
        ]);
    }

    private function getHistorySourceKey(): string
    {
        return match ($this->trainingSource) {
            'csv_upload' => 'csv_upload:' . ($this->uploadedCsvPath ?? ''),
            'live_db'    => 'live_db:' . (Auth::id() ?? ''),
            default      => 'csv_server',
        };
    }

    private function rebuildHistory(): void
    {
        $reader               = $this->csvReader ?? new CsvDataReader();
        $this->roomHistory    = $this->buildTimeSeries('room',    $reader);
        $this->vehicleHistory = $this->buildTimeSeries('vehicle', $reader);
        $this->historySourceKey = $this->getHistorySourceKey();

        if (config('app.debug')) {
            Log::info('OccupancyForecasting: history rebuilt', [
                'source'     => $this->trainingSource,
                'source_key' => $this->historySourceKey,
                'room_rows'  => count($this->roomHistory),
                'vehicle_rows' => count($this->vehicleHistory),
            ]);
        }
    }

    private function buildTimeSeries(string $type, CsvDataReader $reader): array
    {
        $csvMetric = match($type) {
            'room'    => 'combined_rooms',
            'vehicle' => 'vehicle_bookings',
            default   => 'visitors',
        };

        switch ($this->trainingSource) {

            case 'csv_upload':
                if ($this->uploadedCsvPath) {
                    try {
                        if ($type === 'room') {
                            return $this->readRoomHistoryFromCsv($this->uploadedCsvPath, $reader);
                        }
                        return $reader->readUploadedCsv($this->uploadedCsvPath, $csvMetric);
                    } catch (\Throwable $e) {
                        Log::warning('OccupancyForecasting: uploaded CSV unreadable, falling back', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                // fallback server CSV if upload is missing
                $this->trainingSource = 'csv_server';

            case 'csv_server':
                if ($type === 'room') {
                    return $this->readRoomHistoryFromServerCsv($reader);
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

    private function readRoomHistoryFromServerCsv(CsvDataReader $reader): array
    {
        try {
            return $reader->readServerCsvColumnsSummed([
                'offline_room_bookings',
                'online_room_bookings',
            ]);
        } catch (\Throwable $e) {
            Log::warning('OccupancyForecasting: failed to read room history from server CSV', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function readRoomHistoryFromCsv(string $storagePath, CsvDataReader $reader): array
    {
        try {
            return $reader->readUploadedCsvColumnsSummed($storagePath, [
                'offline_room_bookings',
                'online_room_bookings',
            ]);
        } catch (\Throwable $e) {
            Log::warning('OccupancyForecasting: failed to read room history from uploaded CSV', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function getRoomHistory(int $companyId): array
    {
        $rows = BookingRoom::where('company_id', $companyId)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->map(fn($r) => ['date' => Carbon::parse((string) $r->date)->format('Y-m-d'), 'count' => (int) $r->count])
            ->toArray();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['date']] = $row['count'];
        }
        
        return $this->fillHistoryGaps($rows, $indexed);
    }

    private function getVehicleHistory(int $companyId): array
    {
        $rows = VehicleBooking::where('company_id', $companyId)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->map(fn($r) => ['date' => Carbon::parse((string) $r->date)->format('Y-m-d'), 'count' => (int) $r->count])
            ->toArray();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['date']] = $row['count'];
        }
        
        return $this->fillHistoryGaps($rows, $indexed);
    }

    private function fillHistoryGaps(array $rows, array $indexed): array
    {
        $minLookback  = max(90, (int) AISettings::get('min_data_points', 45) + 30);
        $lookbackDate = Carbon::today()->subDays($minLookback);
        $earliestDate = !empty($rows) ? Carbon::parse($rows[0]['date']) : $lookbackDate;
        $start        = $earliestDate->lt($lookbackDate) ? $earliestDate : $lookbackDate;
        $end          = Carbon::today();
        $result       = [];

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dateStr  = $d->format('Y-m-d');
            $result[] = ['date' => $dateStr, 'count' => $indexed[$dateStr] ?? 0];
        }

        return $result;
    }

    private function movingAverageForecast(array $history, int $days, array $settings): array
    {
        $window     = (int)   $settings['ma_window'];
        $lowerMult  = (float) $settings['ma_lower_bound'];
        $upperMult  = (float) $settings['ma_upper_bound'];
        $confidence = (float) $settings['ma_confidence'];
        $floorAvg   = (float) $settings['ma_floor_avg'];

        if (empty($history)) {
            $avg = $floorAvg;
        } else {
            $slice = array_slice($history, -$window);
            $avg   = array_sum(array_column($slice, 'count')) / count($slice);
        }

        $dowTotals = array_fill(0, 7, 0.0);
        $dowCounts = array_fill(0, 7, 0);

        foreach ($history as $row) {
            $dow = (int) date('w', strtotime($row['date']));
            $dowTotals[$dow] += (float) $row['count'];
            $dowCounts[$dow]++;
        }

        $dowAvg = [];
        for ($d = 0; $d < 7; $d++) {
            $dowAvg[$d] = $dowCounts[$d] > 0
                ? $dowTotals[$d] / $dowCounts[$d]
                : $avg;
        }

        $overallHistAvg = $avg > 0 ? $avg : $floorAvg;
        $dowMultiplier  = [];
        for ($d = 0; $d < 7; $d++) {
            $dowMultiplier[$d] = $dowAvg[$d] / $overallHistAvg;
        }

        $today    = date('Y-m-d');
        $forecast = [];

        for ($i = 1; $i <= $days; $i++) {
            $date      = date('Y-m-d', strtotime($today . " +{$i} days"));
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
        $labels      = [];
        $roomData    = [];
        $vehicleData = [];
        $base        = $room ?? $vehicle ?? [];
        $hasRoom     = $room    !== null;
        $hasVehicle  = $vehicle !== null;

        foreach ($base as $i => $p) {
            $labels[]      = Carbon::parse($p['date'])->format('d/m');
            $roomData[]    = $hasRoom    ? round($p['predicted'], 1) : null;
            $vehicleData[] = $hasVehicle ? round(($vehicle[$i]['predicted'] ?? 0), 1) : null;
        }

        $firstLabel = $labels[0] ?? 'none';
        $lastLabel  = $labels[count($labels) - 1] ?? 'none';
        $firstRoomDate    = $room    ? ($room[0]['date'] ?? 'none')    : 'n/a';
        $firstVehicleDate = $vehicle ? ($vehicle[0]['date'] ?? 'none') : 'n/a';

        if (config('app.debug')) {
            Log::info('OccupancyForecasting: buildChartData() labels derived from prediction.date', [
                'total_labels'       => count($labels),
                'first_label'        => $firstLabel,
                'last_label'         => $lastLabel,
                'first_room_date'    => $firstRoomDate,
                'first_vehicle_date' => $firstVehicleDate,
                'labels_sample'      => array_slice($labels, 0, 5),
            ]);
        }

        return compact('labels', 'roomData', 'vehicleData');
    }

    private function buildStats(array $roomHist, array $vehicleHist, ?array $roomFc, ?array $vehicleFc): array
    {
        $avgRoomHist    = $this->avg(array_column($roomHist, 'count'));
        $avgVehicleHist = $this->avg(array_column($vehicleHist, 'count'));
        
        $roomFcCount    = is_array($roomFc)    ? count($roomFc)    : 0;
        $vehicleFcCount = is_array($vehicleFc) ? count($vehicleFc) : 0;
        
        $roomPredictions    = ($roomFcCount    > 0) ? array_column($roomFc,    'predicted') : [];
        $vehiclePredictions = ($vehicleFcCount > 0) ? array_column($vehicleFc, 'predicted') : [];
        
        $roomPredictions    = array_filter($roomPredictions, fn($v) => $v !== null);
        $vehiclePredictions = array_filter($vehiclePredictions, fn($v) => $v !== null);
        
        if (config('app.debug')) {
            Log::info('OccupancyForecasting: buildStats() prediction extraction', [
                'room_fc_is_array'        => is_array($roomFc),
                'room_fc_count'           => $roomFcCount,
                'room_predictions_count'  => count($roomPredictions),
                'room_predictions_sample' => array_slice($roomPredictions, 0, 3),
                'vehicle_fc_is_array'        => is_array($vehicleFc),
                'vehicle_fc_count'           => $vehicleFcCount,
                'vehicle_predictions_count'  => count($vehiclePredictions),
                'vehicle_predictions_sample' => array_slice($vehiclePredictions, 0, 3),
            ]);
        }
        
        $avgRoomFc      = !empty($roomPredictions)    ? $this->avg($roomPredictions)    : null;
        $avgVehicleFc   = !empty($vehiclePredictions) ? $this->avg($vehiclePredictions) : null;

        $roomTrend    = ($avgRoomFc !== null && $avgRoomHist > 0)
            ? round(($avgRoomFc - $avgRoomHist) / $avgRoomHist * 100, 1) : 0;
        $vehicleTrend = ($avgVehicleFc !== null && $avgVehicleHist > 0)
            ? round(($avgVehicleFc - $avgVehicleHist) / $avgVehicleHist * 100, 1) : 0;

        $peakDay = null;
        if (!empty($roomPredictions)) {
            $max = max($roomPredictions);
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
            'avg_room_fc'      => $avgRoomFc    !== null ? round($avgRoomFc, 1)    : '—',
            'avg_vehicle_fc'   => $avgVehicleFc !== null ? round($avgVehicleFc, 1) : '—',
            'room_trend'       => $roomTrend,
            'vehicle_trend'    => $vehicleTrend,
            'peak_day'         => $peakDay ?? '—',
            'total_room_fc'    => !empty($roomPredictions)    ? round(array_sum($roomPredictions))    : '—',
            'total_vehicle_fc' => !empty($vehiclePredictions) ? round(array_sum($vehiclePredictions)) : '—',
        ];
    }

    private function avg(array $values): float
    {
        return count($values) > 0 ? array_sum($values) / count($values) : 0;
    }
}
