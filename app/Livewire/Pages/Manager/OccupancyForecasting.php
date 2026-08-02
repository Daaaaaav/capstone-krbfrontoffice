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
    public string $trainingSource   = 'csv_server'; // csv_server (default) | csv_upload | live_db
    public        $uploadedCsv      = null;
    public ?string $uploadedCsvPath = null;   
    public ?string $uploadedCsvName = null;
    public ?string $uploadError     = null;
    public ?string $uploadSuccess   = null;
    public ?array  $csvInfo         = null;

    public function setForecastType(string $type): void
    {
        $this->forecastType = $type;
    }

    public function setForecastDays(int $days): void
    {
        $this->forecastDays = $days;
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

            $reader  = new CsvDataReader();
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

            // remove old upload if one exists
            if ($this->uploadedCsvPath) {
                Storage::disk(CsvDataReader::DISK)->delete($this->uploadedCsvPath);
            }

            $this->uploadedCsvPath = $tmpPath;
            $this->uploadedCsvName = $this->uploadedCsv->getClientOriginalName();
            $this->uploadedCsv     = null;
            $this->trainingSource  = 'csv_upload';
            $this->uploadSuccess   = __('app.csv_upload_success', ['name' => $this->uploadedCsvName]);

            Log::info('OccupancyForecasting: CSV uploaded', [
                'path' => $tmpPath,
                'name' => $this->uploadedCsvName,
            ]);

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
        $companyId = Auth::user()->company_id;

        $reader = new CsvDataReader();

        $this->csvInfo = $reader->serverCsvInfo();

        $roomHistory    = $this->buildTimeSeries('room',    $reader);
        $vehicleHistory = $this->buildTimeSeries('vehicle', $reader);

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
            // fallback simple moving-average projection
            if (in_array($this->forecastType, ['room', 'combined'])) {
                $roomForecast = $this->movingAverageForecast($roomHistory, $this->forecastDays);
            }
            if (in_array($this->forecastType, ['vehicle', 'combined'])) {
                $vehicleForecast = $this->movingAverageForecast($vehicleHistory, $this->forecastDays);
            }
        }

        $chartData = $this->buildChartData($roomForecast, $vehicleForecast);

        $stats = $this->buildStats($roomHistory, $vehicleHistory, $roomForecast, $vehicleForecast);

        $modelMetrics = $isAvailable ? $lstm->getModelMetrics() : null;

        Log::info('OccupancyForecasting: metrics pipeline trace', [
            'lstm_available'       => $isAvailable,
            'model_metrics_null'   => is_null($modelMetrics),
            'metrics_available'    => $modelMetrics['available'] ?? false,
            'metrics_trained_at'   => $modelMetrics['trained_at'] ?? 'null',
            'metrics_mae'          => $modelMetrics['mae'] ?? 'null',
            'metrics_rmse'         => $modelMetrics['rmse'] ?? 'null',
            'metrics_epochs'       => $modelMetrics['epochs_run'] ?? 'null',
        ]);

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

    private function buildTimeSeries(string $type, ?CsvDataReader $reader = null): array
    {
        $reader = $reader ?? new CsvDataReader();

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

    private function readRoomHistoryFromServerCsv(?CsvDataReader $reader = null): array
    {
        $reader = $reader ?? new CsvDataReader();
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

    private function readRoomHistoryFromCsv(string $storagePath, ?CsvDataReader $reader = null): array
    {
        $reader = $reader ?? new CsvDataReader();
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
        $settings   = AISettings::getMultiple([
            'ma_window'      => 7,
            'ma_lower_bound' => 0.8,
            'ma_upper_bound' => 1.2,
            'ma_confidence'  => 0.60,
            'ma_floor_avg'   => 3.0,
        ]);
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

        // Always anchor to today so forecast dates are never stale relative
        // to the historical CSV end date.
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
            $labels[]    = date('d/m', strtotime($p['date']));
            $roomData[]  = $hasRoom    ? round($p['predicted'], 1) : null;
            $vehicleData[] = $hasVehicle ? round($vehicle[$i]['predicted'], 1) : null;
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
            'avg_room_fc'      => $avgRoomFc    ? round($avgRoomFc, 1)    : '—',
            'avg_vehicle_fc'   => $avgVehicleFc ? round($avgVehicleFc, 1) : '—',
            'room_trend'       => $roomTrend,
            'vehicle_trend'    => $vehicleTrend,
            'peak_day'         => $peakDay ?? '—',
            'total_room_fc'    => $roomFc    ? round(array_sum(array_column($roomFc, 'predicted')))    : '—',
            'total_vehicle_fc' => $vehicleFc ? round(array_sum(array_column($vehicleFc, 'predicted'))) : '—',
        ];
    }

    private function avg(array $values): float
    {
        return count($values) > 0 ? array_sum($values) / count($values) : 0;
    }
}
