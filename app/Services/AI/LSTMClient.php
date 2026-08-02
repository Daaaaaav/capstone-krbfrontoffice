<?php

namespace App\Services\AI;

use App\Models\AISettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LSTMClient
{
    private string $baseUrl;
    private int    $timeout;
    private int    $minimumDataPoints;

    public function __construct()
    {
        $this->baseUrl           = env('LSTM_SERVICE_URL', 'http://127.0.0.1:8001');
        $this->timeout           = (int) env('LSTM_SERVICE_TIMEOUT', 30);
        $this->minimumDataPoints = AISettings::get('min_data_points', 45);
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $headers = ['Accept' => 'application/json'];

        // if (str_contains($this->baseUrl, 'ngrok')) {
        //     $headers['ngrok-skip-browser-warning'] = '1';
        // }

        return \Illuminate\Support\Facades\Http::timeout($this->timeout)
            ->withHeaders($headers);
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->http()->timeout(2)->get($this->baseUrl . '/');
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('LSTM service unavailable', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function predict(array $timeSeries, int $forecastDays = 7, bool $useDummyData = false): ?array
    {
        try {
            if (!$useDummyData && count($timeSeries) < $this->minimumDataPoints) {
                Log::warning('Insufficient historical data for LSTM forecast', [
                    'required' => $this->minimumDataPoints,
                    'received' => count($timeSeries),
                ]);
                return null;
            }

            $data = array_map(fn ($p) => [
                'date'  => $p['date'],
                'count' => (float) $p['count'],
            ], $timeSeries);

            $payload = [
                'data'           => $data,
                'forecast_days'  => $forecastDays,
                'lstm_config'    => AISettings::group('lstm'),
            ];

            $response = $this->http()
                ->post($this->baseUrl . '/predict', $payload);

            if (!$response->successful()) {
                Log::warning('LSTM service returned unsuccessful response', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $result = $response->json();

            Log::info('LSTM prediction generated', [
                'model'            => $result['model'] ?? 'unknown',
                'rmse'             => $result['metrics']['rmse'] ?? null,
                'training_samples' => $result['training_samples'] ?? null,
            ]);

            return [
                'method'           => 'lstm',
                'model'            => $result['model'] ?? 'Improved LSTM Forecast Model',
                'rmse'             => $result['metrics']['rmse'] ?? $result['rmse'] ?? 0,
                'metrics'          => $result['metrics'] ?? ['rmse' => 0, 'mae' => 0, 'mape' => 0],
                'features_used'    => $result['features_used'] ?? [],
                'predictions'      => $result['predictions'] ?? [],
                'data_source'      => $result['data_source'] ?? 'unknown',
                'weekly_summary'   => $result['weekly_summary'] ?? null,
                'training_samples' => $result['training_samples'] ?? 0,
                'test_samples'     => $result['test_samples'] ?? 0,
            ];

        } catch (\Exception $e) {
            Log::error('LSTM prediction failed', [
                'error'         => $e->getMessage(),
                'url'           => $this->baseUrl,
                'forecast_days' => $forecastDays,
            ]);
            return null;
        }
    }

    public function predict3Weeks(array $timeSeries = [], bool $useDummyData = false): ?array
    {
        try {
            if (empty($timeSeries) || count($timeSeries) < $this->minimumDataPoints) {
                $useDummyData = true;
                $timeSeries   = [['date' => date('Y-m-d'), 'count' => 0]];
            }

            $data = array_map(fn ($p) => [
                'date'  => $p['date'],
                'count' => (float) $p['count'],
            ], $timeSeries);

            $payload = [
                'data'           => $data,
                'forecast_days'  => 21,
                'use_dummy_data' => $useDummyData,
                'lstm_config'    => AISettings::group('lstm'),
            ];

            $response = $this->http()
                ->post($this->baseUrl . '/predict-3weeks', $payload);

            if (!$response->successful()) {
                Log::warning('3-week forecast request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('LSTM 3-week prediction failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getModelMetrics(): ?array
    {
        try {
            Log::info('LSTMClient: calling GET /model-metrics', ['url' => $this->baseUrl . '/model-metrics']);

            $response = $this->http()->timeout(5)->get($this->baseUrl . '/model-metrics');

            if (!$response->successful()) {
                Log::warning('LSTM /model-metrics returned non-200', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);
                return null;
            }

            $data = $response->json();

            Log::info('LSTMClient: /model-metrics response received', [
                'available'  => $data['available'] ?? false,
                'trained_at' => $data['trained_at'] ?? 'null',
                'mae'        => $data['mae'] ?? 'null',
                'rmse'       => $data['rmse'] ?? 'null',
                'epochs_run' => $data['epochs_run'] ?? 'null',
            ]);

            return $data;

        } catch (\Exception $e) {
            Log::warning('LSTM getModelMetrics failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getDemo(): ?array
    {
        try {
            $response = $this->http()->get($this->baseUrl . '/demo');

            if (!$response->successful()) {
                Log::warning('LSTM demo endpoint failed', ['status' => $response->status()]);
                return null;
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('LSTM demo failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function forceRetrain(array $timeSeries, int $forecastDays = 7): ?array
    {
        try {
            $data = array_map(fn ($p) => [
                'date'  => $p['date'],
                'count' => (float) $p['count'],
            ], $timeSeries);

            $payload = [
                'data'           => $data,
                'forecast_days'  => $forecastDays,
                'use_dummy_data' => false,
                'force_retrain'  => true,
                'lstm_config'    => AISettings::group('lstm'),
            ];

            $response = $this->http()
                ->timeout(max($this->timeout, 120))
                ->post($this->baseUrl . '/retrain', $payload);

            if (!$response->successful()) {
                Log::warning('LSTM retrain returned unsuccessful response', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            Log::info('LSTM model retrained successfully');
            return $response->json();

        } catch (\Exception $e) {
            Log::error('LSTM force retrain failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function predictWithFallback(array $timeSeries, int $forecastDays = 7): array
    {
        $lstmResult = $this->predict($timeSeries, $forecastDays);

        if ($lstmResult !== null) {
            return $lstmResult;
        }

        Log::info('Using fallback moving average forecast');
        return $this->simpleMovingAverage($timeSeries, $forecastDays);
    }

    private function simpleMovingAverage(array $timeSeries, int $forecastDays): array
    {
        if (empty($timeSeries)) {
            return [
                'method'      => 'fallback',
                'model'       => 'Simple Moving Average',
                'metrics'     => ['rmse' => 0, 'mae' => 0, 'mape' => 0],
                'predictions' => [],
            ];
        }

        $windowSize     = AISettings::get('ma_window', 7);
        $weekendFactor  = AISettings::get('ma_weekend_factor', 0.9);
        $lowerMult      = AISettings::get('ma_lower_bound', 0.8);
        $upperMult      = AISettings::get('ma_upper_bound', 1.2);
        $fixedConfidence = AISettings::get('ma_confidence', 0.60);

        $windowSize = min((int) $windowSize, count($timeSeries));
        $recentData = array_slice($timeSeries, -(int) $windowSize);
        $avgCount   = array_sum(array_column($recentData, 'count')) / $windowSize;

        $trend = 0;
        if (count($recentData) >= 2) {
            $first = $recentData[0]['count'];
            $last  = end($recentData)['count'];
            $trend = ($last - $first) / count($recentData);
        }

        $predictions = [];
        $today = date('Y-m-d');

        for ($i = 1; $i <= $forecastDays; $i++) {
            $nextDate   = date('Y-m-d', strtotime($today . " +{$i} days"));
            $prediction = $avgCount + ($trend * $i);

            if (date('N', strtotime($nextDate)) >= 6) {
                $prediction *= (float) $weekendFactor;
            }

            $prediction = max(0, round($prediction, 1));

            $predictions[] = [
                'date'        => $nextDate,
                'predicted'   => $prediction,
                'lower_bound' => max(0, round($prediction * (float) $lowerMult, 1)),
                'upper_bound' => round($prediction * (float) $upperMult, 1),
                'confidence'  => (float) $fixedConfidence,
            ];
        }

        return [
            'method'        => 'fallback',
            'model'         => 'Simple Moving Average',
            'metrics'       => ['rmse' => 0, 'mae' => 0, 'mape' => 0],
            'predictions'   => $predictions,
            'features_used' => ['moving_average', 'trend_estimation', 'weekend_adjustment'],
        ];
    }
}
