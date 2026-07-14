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

    /**
     * Build an HTTP client pre-configured for the LSTM service.
     * When the service URL is an ngrok tunnel, the browser-warning bypass
     * header is automatically injected so requests reach FastAPI directly.
     */
    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $headers = ['Accept' => 'application/json'];

        // ngrok free-tier tunnels show a browser warning page unless this
        // header is present. It has no effect on non-ngrok endpoints.
        if (str_contains($this->baseUrl, 'ngrok')) {
            $headers['ngrok-skip-browser-warning'] = '1';
        }

        return \Illuminate\Support\Facades\Http::timeout($this->timeout)
            ->withHeaders($headers);
    }

    /**
     * Check if the LSTM service is reachable.
     */
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

    /**
     * Generate AI forecast predictions.
     *
     * @param  array $timeSeries   Array of ['date' => 'Y-m-d', 'count' => int]
     * @param  int   $forecastDays
     * @param  bool  $useDummyData
     * @return array|null
     */
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
                'use_dummy_data' => $useDummyData,
                // Pass all LSTM hyperparameters from the database
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

    /**
     * Generate a 21-day (3-week) forecast.
     *
     * @param  array $timeSeries
     * @param  bool  $useDummyData
     * @return array|null
     */
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

    /**
     * Call the demo endpoint (always uses dummy data).
     */
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

    /**
     * Force a full model retrain regardless of the cached fingerprint.
     * POSTs to the /retrain endpoint which sets force_retrain=true.
     *
     * @param  array $timeSeries
     * @param  int   $forecastDays
     * @return array|null
     */
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
                ->timeout(max($this->timeout, 120)) // retrain can take longer
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

    /**
     * Try LSTM first; fall back to simple moving average if unavailable.
     */
    public function predictWithFallback(array $timeSeries, int $forecastDays = 7): array
    {
        $lstmResult = $this->predict($timeSeries, $forecastDays);

        if ($lstmResult !== null) {
            return $lstmResult;
        }

        Log::info('Using fallback moving average forecast');
        return $this->simpleMovingAverage($timeSeries, $forecastDays);
    }

    /**
     * Simple moving-average fallback when LSTM is unavailable.
     * All magic numbers are read from ai_settings.
     */
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

        // Read fallback config from DB (with hardcoded defaults as last resort)
        $windowSize     = AISettings::get('ma_window', 7);
        $weekendFactor  = AISettings::get('ma_weekend_factor', 0.9);
        $lowerMult      = AISettings::get('ma_lower_bound', 0.8);
        $upperMult      = AISettings::get('ma_upper_bound', 1.2);
        $fixedConfidence = AISettings::get('ma_confidence', 0.60);

        $windowSize = min((int) $windowSize, count($timeSeries));
        $recentData = array_slice($timeSeries, -(int) $windowSize);
        $avgCount   = array_sum(array_column($recentData, 'count')) / $windowSize;

        // Simple trend: slope over the window
        $trend = 0;
        if (count($recentData) >= 2) {
            $first = $recentData[0]['count'];
            $last  = end($recentData)['count'];
            $trend = ($last - $first) / count($recentData);
        }

        $predictions = [];
        $lastDate    = end($timeSeries)['date'];

        for ($i = 1; $i <= $forecastDays; $i++) {
            $nextDate   = date('Y-m-d', strtotime($lastDate . " +{$i} days"));
            $prediction = $avgCount + ($trend * $i);

            // Weekend demand adjustment
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
