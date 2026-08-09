<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\LSTMClient;
use App\Services\AI\CsvDataReader;
use Illuminate\Support\Facades\Log;

class TestForecastStats extends Command
{
    protected $signature = 'test:forecast-stats';
    protected $description = 'Test forecast stats calculation to debug dashboard cards';

    public function handle()
    {
        $this->info('Testing forecast stats calculation...');
        $this->newLine();

        // 1. Check LSTM availability
        $lstm = app(LSTMClient::class);
        $isAvailable = $lstm->isAvailable();
        
        $this->info('LSTM Service Status: ' . ($isAvailable ? 'AVAILABLE ✓' : 'UNAVAILABLE ✗'));
        $this->newLine();

        if (!$isAvailable) {
            $this->error('LSTM service is not available. Cannot test forecast stats.');
            return 1;
        }

        // 2. Load historical data
        $reader = new CsvDataReader();
        $csvInfo = $reader->serverCsvInfo();
        
        $this->info('CSV Data: ' . ($csvInfo['rows'] ?? 0) . ' rows');
        $this->info('Date Range: ' . ($csvInfo['start'] ?? 'unknown') . ' → ' . ($csvInfo['end'] ?? 'unknown'));
        $this->newLine();

        // Read room history (offline + online bookings summed)
        $roomHistory = $reader->readServerCsvColumnsSummed([
            'offline_room_bookings',
            'online_room_bookings',
        ]);

        // Read vehicle history
        $vehicleHistory = $reader->readServerCsv('vehicle_bookings');

        $this->info('Historical Data:');
        $this->line('  Room data points: ' . count($roomHistory));
        $this->line('  Vehicle data points: ' . count($vehicleHistory));
        
        if (count($roomHistory) > 0) {
            $this->line('  First room entry: ' . $roomHistory[0]['date'] . ' (count: ' . $roomHistory[0]['count'] . ')');
            $this->line('  Last room entry: ' . $roomHistory[count($roomHistory) - 1]['date'] . ' (count: ' . $roomHistory[count($roomHistory) - 1]['count'] . ')');
        }
        $this->newLine();

        // 3. Get LSTM predictions
        $forecastDays = 21;
        
        $this->info('Requesting LSTM predictions for ' . $forecastDays . ' days...');
        $roomResult = $lstm->predict($roomHistory, $forecastDays, false);
        $vehicleResult = $lstm->predict($vehicleHistory, $forecastDays, false);

        // 4. Extract predictions
        $roomForecast = $roomResult['predictions'] ?? null;
        $vehicleForecast = $vehicleResult['predictions'] ?? null;

        $this->newLine();
        $this->info('LSTM Response Analysis:');
        $this->line('  Room result is null: ' . (is_null($roomResult) ? 'YES' : 'NO'));
        $this->line('  Room result keys: ' . (is_array($roomResult) ? implode(', ', array_keys($roomResult)) : 'n/a'));
        $this->line('  Room forecast is null: ' . (is_null($roomForecast) ? 'YES' : 'NO'));
        $this->line('  Room forecast count: ' . (is_array($roomForecast) ? count($roomForecast) : 'n/a'));
        
        if (is_array($roomForecast) && count($roomForecast) > 0) {
            $this->line('  First room prediction: ' . json_encode($roomForecast[0]));
            $this->line('  Last room prediction: ' . json_encode($roomForecast[count($roomForecast) - 1]));
        }

        $this->newLine();
        $this->line('  Vehicle result is null: ' . (is_null($vehicleResult) ? 'YES' : 'NO'));
        $this->line('  Vehicle result keys: ' . (is_array($vehicleResult) ? implode(', ', array_keys($vehicleResult)) : 'n/a'));
        $this->line('  Vehicle forecast is null: ' . (is_null($vehicleForecast) ? 'YES' : 'NO'));
        $this->line('  Vehicle forecast count: ' . (is_array($vehicleForecast) ? count($vehicleForecast) : 'n/a'));

        // 5. Calculate stats (same logic as buildStats)
        $roomPredictions = (is_array($roomForecast) && count($roomForecast) > 0) ? array_column($roomForecast, 'predicted') : [];
        $vehiclePredictions = (is_array($vehicleForecast) && count($vehicleForecast) > 0) ? array_column($vehicleForecast, 'predicted') : [];

        $this->newLine();
        $this->info('Prediction Extraction:');
        $this->line('  Room predictions extracted: ' . count($roomPredictions));
        if (count($roomPredictions) > 0) {
            $this->line('  Room predictions sample: ' . implode(', ', array_slice($roomPredictions, 0, 5)));
        }
        
        $this->line('  Vehicle predictions extracted: ' . count($vehiclePredictions));
        if (count($vehiclePredictions) > 0) {
            $this->line('  Vehicle predictions sample: ' . implode(', ', array_slice($vehiclePredictions, 0, 5)));
        }

        // 6. Calculate averages
        $avgRoomFc = !empty($roomPredictions) ? round(array_sum($roomPredictions) / count($roomPredictions), 1) : null;
        $avgVehicleFc = !empty($vehiclePredictions) ? round(array_sum($vehiclePredictions) / count($vehiclePredictions), 1) : null;

        $this->newLine();
        $this->info('Calculated Stats:');
        $this->line('  Avg Room Occupancy Forecast: ' . ($avgRoomFc !== null ? $avgRoomFc : '—'));
        $this->line('  Avg Vehicle Bookings Forecast: ' . ($avgVehicleFc !== null ? $avgVehicleFc : '—'));
        $this->line('  Total Room Forecast: ' . (!empty($roomPredictions) ? round(array_sum($roomPredictions)) : '—'));
        $this->line('  Total Vehicle Forecast: ' . (!empty($vehiclePredictions) ? round(array_sum($vehiclePredictions)) : '—'));

        // Peak day
        if (!empty($roomPredictions)) {
            $max = max($roomPredictions);
            foreach ($roomForecast as $p) {
                if (round($p['predicted'], 1) === round($max, 1)) {
                    $peakDay = \Carbon\Carbon::parse($p['date'])->isoFormat('ddd, D MMM');
                    $this->line('  Peak Day: ' . $peakDay . ' (' . $p['predicted'] . ' bookings)');
                    break;
                }
            }
        } else {
            $this->line('  Peak Day: —');
        }

        $this->newLine();
        $this->info('Test complete!');
        
        return 0;
    }
}
