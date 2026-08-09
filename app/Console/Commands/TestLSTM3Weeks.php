<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\LSTMClient;

class TestLSTM3Weeks extends Command
{
    protected $signature = 'ai:test-3weeks {--dummy : Use dummy data}';
    protected $description = 'Test LSTM 3-week predictions with dummy booking data';

    public function handle()
    {
        $this->info('===========================================');
        $this->info('LSTM 3-Week Prediction Test');
        $this->info('===========================================');
        $this->newLine();

        $lstmClient = new LSTMClient();

        $this->info('Checking LSTM Service...');
        if (!$lstmClient->isAvailable()) {
            $this->error('✗ LSTM service is not running!');
            $this->warn('Please start the service: start_lstm_service.bat');
            return 1;
        }
        $this->info('✓ LSTM service is running');
        $this->newLine();

        $this->info('Fetching 3-week prediction with dummy data...');
        $result = $lstmClient->getDemo();

        if (!$result) {
            $this->error('✗ Failed to get predictions');
            return 1;
        }

        $this->info('===========================================');
        $this->line($result['title'] ?? 'LSTM Predictions');
        $this->info('===========================================');
        $this->newLine();

        if (isset($result['description'])) {
            $this->line($result['description']);
            $this->newLine();
        }

        $this->info('Model Information:');
        $this->line("  Model: " . ($result['model'] ?? 'LSTM'));
        $this->line("  Training Data: " . ($result['training_data_points'] ?? 'N/A') . " days");
        $this->line("  Forecast Period: " . ($result['forecast_days'] ?? 21) . " days");
        $this->line("  RMSE: " . number_format($result['rmse'] ?? 0, 4));
        $this->line("  Data Source: " . ($result['data_source'] ?? 'unknown'));
        $this->newLine();

        if (isset($result['weekly_summary'])) {
            $this->info('Weekly Summary:');
            $this->table(
                ['Week', 'Period', 'Avg Daily', 'Weekly Total', 'Range'],
                array_map(function($week) {
                    return [
                        'Week ' . $week['week'],
                        $week['start_date'] . ' to ' . $week['end_date'],
                        number_format($week['avg_predicted'], 1),
                        number_format($week['total_predicted'], 0),
                        number_format($week['avg_lower_bound'], 1) . ' - ' . number_format($week['avg_upper_bound'], 1),
                    ];
                }, $result['weekly_summary'])
            );
            $this->newLine();
        }

        if (isset($result['predictions']) && !empty($result['predictions'])) {
            $this->info('Daily Predictions (First 7 days):');
            
            $predictions = array_slice($result['predictions'], 0, 7);
            $this->table(
                ['Date', 'Day', 'Predicted', 'Lower Bound', 'Upper Bound', 'Confidence'],
                array_map(function($pred) {
                    return [
                        $pred['date'],
                        date('l', strtotime($pred['date'])),
                        number_format($pred['predicted'], 1),
                        number_format($pred['lower_bound'], 1),
                        number_format($pred['upper_bound'], 1),
                        number_format($pred['confidence'] * 100, 1) . '%',
                    ];
                }, $predictions)
            );
            $this->newLine();

            $allPredictions = $result['predictions'];
            $totalPredicted = array_sum(array_column($allPredictions, 'predicted'));
            $avgDaily = $totalPredicted / count($allPredictions);
            $avgConfidence = array_sum(array_column($allPredictions, 'confidence')) / count($allPredictions);

            $this->info('Summary Statistics:');
            $this->line("  Total Predicted (21 days): " . number_format($totalPredicted, 0) . " bookings");
            $this->line("  Average Daily: " . number_format($avgDaily, 1) . " bookings");
            $this->line("  Average Confidence: " . number_format($avgConfidence * 100, 1) . "%");
        }

        $this->newLine();
        $this->info('===========================================');
        $this->info('Test completed successfully!');
        $this->info('===========================================');

        return 0;
    }
}