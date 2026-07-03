<?php

namespace App\Livewire\Pages\Superadmin;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\BookingRoom;
use App\Models\VehicleBooking;
use App\Models\Guestbook;
use App\Models\AISettings;
use App\Services\AI\LSTMClient;
use App\Services\WeatherService;
use Carbon\Carbon;

#[Layout('layouts.superadmin')]
#[Title('Occupancy Forecasting')]
class OccupancyForecasting extends Component
{
    use WithFileUploads;
    public string $forecastType     = 'room';   // choice of room | vehicle | combined
    public int    $forecastDays     = 21;      // default to 21 days
    public string $trainingSource   = 'csv_server'; // csv_server | csv_upload | live_db
    public        $uploadedCsv      = null;
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
        $this->validate(['uploadedCsv' => 'required|file|mimes:csv,txt|max:5120']);

        try {
            $path = $this->uploadedCsv->store('csv-uploads', 'local');
            $this->uploadedCsvName = $this->uploadedCsv->getClientOriginalName();
            $this->uploadSuccess   = __('app.csv_upload_success', ['name' => $this->uploadedCsvName]);
            $this->uploadError     = null;

            // Store path in session so the forecasting can reference it
            session(['occupancy_csv_upload_path' => storage_path('app/' . $path)]);
        } catch (\Throwable $e) {
            $this->uploadError   = __('app.csv_upload_failed') . ': ' . $e->getMessage();
            $this->uploadSuccess = null;
        }
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        // ── CSV INFO for server csv ───────────────────────────────────────────
        $csvPath = storage_path('app/lstm_data/capstone_historical_data.csv');
        if (file_exists($csvPath)) {
            $csv = array_map('str_getcsv', file($csvPath));
            $header = array_shift($csv);
            $this->csvInfo = [
                'rows' => count($csv),
                'start' => $csv[0][0] ?? '—',
                'end'   => $csv[count($csv) - 1][0] ?? '—',
            ];
        }

        // ── Determine data source for training ────────────────────────────────
        $roomHistory    = [];
        $vehicleHistory = [];

        if ($this->trainingSource === 'csv_server') {
            // Read from server CSV
            $roomHistory    = $this->getRoomHistoryFromCsv($csvPath);
            $vehicleHistory = $this->getVehicleHistoryFromCsv($csvPath);
        } elseif ($this->trainingSource === 'csv_upload' && session('occupancy_csv_upload_path')) {
            // Read from uploaded CSV
            $uploadPath     = session('occupancy_csv_upload_path');
            $roomHistory    = $this->getRoomHistoryFromCsv($uploadPath);
            $vehicleHistory = $this->getVehicleHistoryFromCsv($uploadPath);
        } else {
            // Default: read from live DB
            $roomHistory    = $this->getRoomHistory($companyId);
            $vehicleHistory = $this->getVehicleHistory($companyId);
        }

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

        // ── Weather (next 3 days from BMKG) 
        // $weather = null;
        // if ($this->withWeather) {
        //     $weatherService = new WeatherService();
        //     $weather = $weatherService->getForecast();
        // }

        // ── Chart data ────────────────────────────────────────────────────────
        $chartData = $this->buildChartData($roomForecast, $vehicleForecast);

        // ── Occupancy stats ───────────────────────────────────────────────────
        $stats = $this->buildStats($roomHistory, $vehicleHistory, $roomForecast, $vehicleForecast);

        return view('livewire.pages.superadmin.occupancy-forecasting', [
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

    // ── CSV Reading helpers ───────────────────────────────────────────────────
    private function getRoomHistoryFromCsv(string $path): array
    {
        if (!file_exists($path)) return [];

        $csv = array_map('str_getcsv', file($path));
        $header = array_shift($csv);

        // Find indices for date, offline_room_bookings, online_room_bookings
        $dateIdx    = array_search('date', $header);
        $offlineIdx = array_search('offline_room_bookings', $header);
        $onlineIdx  = array_search('online_room_bookings', $header);

        if ($dateIdx === false) return [];

        $history = [];
        foreach ($csv as $row) {
            $date    = $row[$dateIdx] ?? null;
            $offline = isset($row[$offlineIdx]) ? (int) $row[$offlineIdx] : 0;
            $online  = isset($row[$onlineIdx])  ? (int) $row[$onlineIdx]  : 0;

            if ($date) {
                $history[] = ['date' => $date, 'count' => $offline + $online];
            }
        }

        return $history;
    }

    private function getVehicleHistoryFromCsv(string $path): array
    {
        if (!file_exists($path)) return [];

        $csv = array_map('str_getcsv', file($path));
        $header = array_shift($csv);

        $dateIdx    = array_search('date', $header);
        $vehicleIdx = array_search('vehicle_bookings', $header);

        if ($dateIdx === false || $vehicleIdx === false) return [];

        $history = [];
        foreach ($csv as $row) {
            $date    = $row[$dateIdx] ?? null;
            $vehicles = isset($row[$vehicleIdx]) ? (int) $row[$vehicleIdx] : 0;

            if ($date) {
                $history[] = ['date' => $date, 'count' => $vehicles];
            }
        }

        return $history;
    }
}
