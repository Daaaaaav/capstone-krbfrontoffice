<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\LSTMClient;
use App\Services\AI\PredictionService;
use App\Services\AI\DataPreprocessor;
use Illuminate\Support\Facades\Auth;

class TestLSTMIntegration extends Command
{
    protected $signature = 'ai:test-lstm {company_id=2}';
    protected $description = 'Test LSTM service integration and predictions';

    public function handle()
    {
        $companyId = $this->argument('company_id');

        $this->info('===========================================');
        $this->info('LSTM Integration Test');
        $this->info('===========================================');
        $this->newLine();

        $this->info('Test 1: Checking LSTM Service...');
        $lstmClient = new LSTMClient();
        $isAvailable = $lstmClient->isAvailable();

        if ($isAvailable) {
            $this->info('✓ LSTM service is RUNNING');
        } else {
            $this->warn('✗ LSTM service is NOT available (will use fallback)');
        }
        $this->newLine();

        $this->info('Test 2: Testing Data Preprocessing...');
        $preprocessor = new DataPreprocessor();
        
        try {
            $timeSeries = $preprocessor->createTimeSeriesDataset('room_booking', $companyId, 30);
            $this->info("✓ Created time series with " . count($timeSeries) . " data points");
            
            if (count($timeSeries) > 0) {
                $this->info("  Sample: {$timeSeries[0]['date']} => {$timeSeries[0]['count']} bookings");
            }
        } catch (\Exception $e) {
            $this->error('✗ Preprocessing failed: ' . $e->getMessage());
        }
        $this->newLine();

        $this->info('Test 3: Making Predictions...');
        $predictionService = new PredictionService();
        
        try {
            $forecast = $predictionService->predictBookingDemand('room_booking', $companyId, 7);
            
            if (!empty($forecast)) {
                $this->info('✓ Predictions generated successfully');
                $this->info("  Method: " . ($forecast[0]['method'] ?? 'unknown'));
                $this->info("  Forecast days: " . count($forecast));
                
                $this->newLine();
                $this->info('Forecast Results:');
                $this->table(
                    ['Date', 'Predicted', 'Confidence', 'Method'],
                    array_map(function($day) {
                        return [
                            $day['date'],
                            $day['predicted_count'],
                            number_format($day['confidence'] * 100, 1) . '%',
                            $day['method'] ?? 'N/A',
                        ];
                    }, array_slice($forecast, 0, 7))
                );
            } else {
                $this->warn('✗ No predictions generated');
            }
        } catch (\Exception $e) {
            $this->error('✗ Prediction failed: ' . $e->getMessage());
        }
        $this->newLine();

        $this->info('Test 4: Testing Anomaly Detection...');
        
        try {
            $anomalies = $predictionService->detectAnomalies('room_booking', $companyId);
            
            if (!empty($anomalies)) {
                $this->info('✓ Found ' . count($anomalies) . ' anomalies');
                
                foreach ($anomalies as $anomaly) {
                    $severity = strtoupper($anomaly['severity']);
                    $this->line("  [{$severity}] {$anomaly['message']}");
                }
            } else {
                $this->info('✓ No anomalies detected (system healthy)');
            }
        } catch (\Exception $e) {
            $this->error('✗ Anomaly detection failed: ' . $e->getMessage());
        }
        $this->newLine();

        $this->info('Test 5: Testing Feature Extraction...');
        
        try {
            $features = $preprocessor->preprocessRoomBookings($companyId, 30);
            
            $this->info('✓ Features extracted successfully');
            $this->info("  Booking frequency: " . number_format($features['booking_frequency'], 2) . " per day");
            $this->info("  Approval rate: " . number_format($features['approval_rate'], 1) . "%");
            $this->info("  Peak hours: " . implode(', ', array_map(fn($h) => $h . ':00', $features['peak_hours'])));
        } catch (\Exception $e) {
            $this->error('✗ Feature extraction failed: ' . $e->getMessage());
        }
        $this->newLine();

        $this->info('===========================================');
        $this->info('Test Summary');
        $this->info('===========================================');
        
        if ($isAvailable) {
            $this->info('✓ LSTM service is operational');
            $this->info('✓ Using advanced deep learning predictions');
        } else {
            $this->warn('⚠ LSTM service unavailable - using fallback methods');
            $this->info('  To start LSTM service, run: start_lstm_service.bat');
        }
        
        $this->newLine();
        $this->info('All tests completed!');

        return 0;
    }
}
