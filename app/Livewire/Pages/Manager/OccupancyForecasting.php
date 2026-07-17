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
    public string $forecastType     = 'combined';   // choice of room | vehicle | combined
    public int    $forecastDays     = 21;      // default to 21 days
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

        // ── Single CsvDataReader instance shared across render ────────────────
        // Previously render() created one reader for csvInfo() and buildTimeSeries()
        // created a second one internally.  One instance is passed down.
        $reader = new CsvDataReader();

        // ── CSV INFO for server csv ───────────────────────────────────────────
        $this->csvInfo = $reader->serverCsvInfo();

        // ── Determine data source for training ────────────────────────────────
        $roomHistory    = $this->buildTimeSeries('room',    $reader);
        $vehicleHistory = $this->buildTimeSeries('vehicle', $reader);

        // ── LSTM forecast ─────────────────────────────────────────────────────
        // LSTMClient is instantiated once; isAvailable() is called once.
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

        // ── Chart data ────────────────────────────────────────────────────────
        $chartData = $this->buildChartData($roomForecast, $vehicleForecast);

        // ── Occupancy stats ───────────────────────────────────────────────────
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
     *
     * Accepts an optional $reader so the caller can pass in an already-created
     * CsvDataReader and avoid a second instantiation within the same request.
     */
    private function buildTimeSeries(string $type, ?CsvDataReader $reader = null): array
    {
        $reader = $reader ?? new CsvDataReader();

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
                            return $this->readRoomHistoryFromCsv($this->uploadedCsvPath, $reader);
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

    /**
     * Read room history from server CSV (combines offline + online columns).
     *
     * Previously called readServerCsv() twice — once for each column — causing
     * the CSV file to be opened, read, and sorted twice.  Now uses
     * readServerCsvColumnsSummed() to do both columns in a single file pass.
     *
     * Accepts an optional $reader to avoid a third instantiation within render().
     */
    private function readRoomHistoryFromServerCsv(?CsvDataReader $reader = null): array
    {
        $reader = $reader ?? new CsvDataReader();
        try {
            // Single file pass: reads offline_room_bookings + online_room_bookings
            // and sums them per date row, replacing the previous two-pass approach.
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

    /**
     * Read room history from uploaded CSV (combines offline + online columns).
     *
     * Same optimisation as readRoomHistoryFromServerCsv(): one file pass instead
     * of two.
     *
     * Accepts an optional $reader to avoid a third instantiation within render().
     */
    private function readRoomHistoryFromCsv(string $storagePath, ?CsvDataReader $reader = null): array
    {
        $reader = $reader ?? new CsvDataReader();
        try {
            // Single file pass: reads offline_room_bookings + online_room_bookings
            // and sums them per date row, replacing the previous two-pass approach.
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

    // ── Private helpers ───────────────────────────────────────────────────────
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
        // All magic numbers read from ai_settings table.
        // Previously called AISettings::get() five times separately — each call
        // hits Cache::remember() individually.  One getMultiple() call reads the
        // shared cache entry once and resolves all five keys in a single pass.
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
        $labels      = [];
        $roomData    = [];
        $vehicleData = [];

        // Use whichever forecast is available for labels.
        // When both are present, they always have the same dates (same forecastDays,
        // same base date), so a single combined loop covers both arrays at once,
        // removing the second foreach that previously traversed $vehicle separately.
        $base        = $room ?? $vehicle ?? [];
        $hasRoom     = $room    !== null;
        $hasVehicle  = $vehicle !== null;

        // If both arrays exist they are the same length (both come from the same
        // forecastDays value), so we can zip them in one pass.
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

    private function buildWeatherInsight(?array $weather, ?array $roomFc): ?array
    {
        if (!$weather || !$roomFc) return null;

        $insights = [];
        foreach ($weather['forecast'] as $day) {
            $date = $day['date'];
            // Find matching forecast day
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
}
