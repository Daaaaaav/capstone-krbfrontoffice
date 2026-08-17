<?php

namespace App\Services\AI;

use App\Models\AISettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LSTMClient
{
    private const CSV_INFO_TTL  = 3600;  
    private const METRICS_TTL   = 3600;  
    private const PREDICT_TTL   = 1800;
    private const AVAIL_TTL     = 10; 

    private string $baseUrl;
    private int    $timeout;
    private int    $minimumDataPoints;

    // Request-scoped cache to prevent duplicate predictions in same request
    private static array $requestCache = [];

    public function __construct()
    {
        $this->baseUrl           = env('LSTM_SERVICE_URL', 'http://127.0.0.1:8001');
        $this->timeout           = (int) env('LSTM_SERVICE_TIMEOUT', 30);
        $this->minimumDataPoints = AISettings::get('min_data_points', 45);
    }

    // ── Internal helpers ────────────────────────────────────────────

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withHeaders(['Accept' => 'application/json']);
    }

    private function datasetHash(array $timeSeries): string
    {
        if (empty($timeSeries)) {
            return 'empty';
        }
        $first = $timeSeries[0]['date'] ?? '';
        $last  = $timeSeries[count($timeSeries) - 1]['date'] ?? '';
        $n     = count($timeSeries);
        $sum   = array_sum(array_column($timeSeries, 'count'));
        return md5("{$first}|{$last}|{$n}|{$sum}");
    }

    private function predictionStartDate(array $timeSeries): string
    {
        if (empty($timeSeries)) {
            return date('Y-m-d');
        }
        $lastHistoricalDate = $timeSeries[count($timeSeries) - 1]['date'] ?? date('Y-m-d');
        return date('Y-m-d', strtotime($lastHistoricalDate . ' +1 day'));
    }

    private function modelFingerprint(): string
    {
        return Cache::remember('lstm.model_fingerprint', 60, function () {
            try {
                $resp = $this->http()->timeout(3)->get($this->baseUrl . '/model-info');
                if ($resp->successful()) {
                    return $resp->json('fingerprint') ?? 'no-fingerprint';
                }
            } catch (\Exception $e) {
                // ignore — fall through to default
            }
            return 'unavailable';
        });
    }

    private function isPredictionStale(?array $cached): bool
    {
        if ($cached === null) {
            return true;
        }
        $predictions = $cached['predictions'] ?? [];
        if (empty($predictions)) {
            return true;
        }
        $firstPredictionDate = $predictions[0]['date'] ?? null;
        if ($firstPredictionDate === null) {
            return true;
        }
        $today = date('Y-m-d');
        if ($firstPredictionDate < $today) {
            Log::warning('LSTMClient: stale prediction detected, invalidating cache', [
                'first_prediction_date' => $firstPredictionDate,
                'today'                 => $today,
            ]);
            return true;
        }
        return false;
    }

    private function bustModelCache(): void
    {
        Cache::forget('lstm.model_fingerprint');
        Cache::forget('lstm.model_metrics');
        Cache::forget('lstm.predict.all');
        
        // Clear request-scoped cache as well when model changes
        self::$requestCache = [];
        
        Log::info('LSTMClient: model cache busted (post-retrain).');
    }
    
    public function isAvailable(): bool
    {
        return Cache::remember('lstm.is_available', self::AVAIL_TTL, function () {
            try {
                $response = $this->http()->timeout(2)->get($this->baseUrl . '/');
                return $response->successful();
            } catch (\Exception $e) {
                Log::warning('LSTM service unavailable', ['error' => $e->getMessage()]);
                return false;
            }
        });
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

            $fp           = $this->modelFingerprint();
            $dhash        = $this->datasetHash($timeSeries);
            $startDate    = $this->predictionStartDate($timeSeries);
            $cacheKey     = "lstm.predict.{$fp}.{$dhash}.{$forecastDays}.{$startDate}." . ($useDummyData ? 'dummy' : 'real');

            // ── Request-scoped cache check (prevents duplicate FastAPI calls in same request) ──
            if (isset(self::$requestCache[$cacheKey])) {
                Log::info('LSTMClient: predict() request-scoped cache HIT (same request)', [
                    'cache_key' => $cacheKey,
                ]);
                return self::$requestCache[$cacheKey];
            }

            $cached = Cache::get($cacheKey);

            if ($cached !== null && $this->isPredictionStale($cached)) {
                Cache::forget($cacheKey);
                Log::info('LSTMClient: predict() cache invalidated due to stale dates', [
                    'cache_key'  => $cacheKey,
                    'start_date' => $startDate,
                ]);
                $cached = null;
            }

            if ($cached !== null) {
                $firstDate = $cached['predictions'][0]['date'] ?? 'unknown';
                $lastDate  = $cached['predictions'][count($cached['predictions']) - 1]['date'] ?? 'unknown';
                Log::info('LSTMClient: predict() persistent cache HIT', [
                    'cache_key'        => $cacheKey,
                    'first_prediction' => $firstDate,
                    'last_prediction'  => $lastDate,
                    'model_fingerprint'=> $fp,
                ]);
                
                // Store in request cache too
                self::$requestCache[$cacheKey] = $cached;
                return $cached;
            }

            Log::info('LSTMClient: predict() cache MISS — calling FastAPI', [
                'cache_key'  => $cacheKey,
                'start_date' => $startDate,
            ]);

            $result = $this->executePrediction($timeSeries, $forecastDays, $useDummyData);

            if ($result !== null) {
                // Store in both persistent and request-scoped cache
                Cache::put($cacheKey, $result, self::PREDICT_TTL);
                self::$requestCache[$cacheKey] = $result;
                
                $firstDate = $result['predictions'][0]['date'] ?? 'unknown';
                $lastDate  = $result['predictions'][count($result['predictions']) - 1]['date'] ?? 'unknown';
                Log::info('LSTMClient: predict() fresh result cached', [
                    'cache_key'        => $cacheKey,
                    'first_prediction' => $firstDate,
                    'last_prediction'  => $lastDate,
                    'model_fingerprint'=> $fp,
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('LSTM prediction failed', [
                'error'         => $e->getMessage(),
                'url'           => $this->baseUrl,
                'forecast_days' => $forecastDays,
            ]);
            return null;
        }
    }

    private function executePrediction(array $timeSeries, int $forecastDays, bool $useDummyData): ?array
    {
        $data = array_map(fn ($p) => [
            'date'  => $p['date'],
            'count' => (float) $p['count'],
        ], $timeSeries);

        $payload = [
            'data'           => $data,
            'forecast_days'  => $forecastDays,
            'lstm_config'    => AISettings::group('lstm'),
        ];

        $response = $this->http()->post($this->baseUrl . '/predict', $payload);

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
    }

    public function predict3Weeks(array $timeSeries = [], bool $useDummyData = false): ?array
    {
        try {
            if (empty($timeSeries) || count($timeSeries) < $this->minimumDataPoints) {
                $useDummyData = true;
                $timeSeries   = [['date' => date('Y-m-d'), 'count' => 0]];
            }

            $fp        = $this->modelFingerprint();
            $dhash     = $this->datasetHash($timeSeries);
            $startDate = $this->predictionStartDate($timeSeries);
            $cacheKey  = "lstm.predict3w.{$fp}.{$dhash}.{$startDate}." . ($useDummyData ? 'dummy' : 'real');

            // ── Request-scoped cache check (prevents duplicate FastAPI calls in same request) ──
            if (isset(self::$requestCache[$cacheKey])) {
                Log::info('LSTMClient: predict3Weeks() request-scoped cache HIT (same request)', [
                    'cache_key' => $cacheKey,
                ]);
                return self::$requestCache[$cacheKey];
            }

            $cached = Cache::get($cacheKey);

            if ($cached !== null && $this->isPredictionStale($cached)) {
                Cache::forget($cacheKey);
                Log::info('LSTMClient: predict3Weeks() cache invalidated due to stale dates', [
                    'cache_key'  => $cacheKey,
                    'start_date' => $startDate,
                ]);
                $cached = null;
            }

            if ($cached !== null) {
                $predictions = $cached['predictions'] ?? [];
                $firstDate   = $predictions[0]['date'] ?? 'unknown';
                $lastDate    = $predictions[count($predictions) - 1]['date'] ?? 'unknown';
                Log::info('LSTMClient: predict3Weeks() persistent cache HIT', [
                    'cache_key'        => $cacheKey,
                    'first_prediction' => $firstDate,
                    'last_prediction'  => $lastDate,
                    'model_fingerprint'=> $fp,
                ]);
                
                // Store in request cache too
                self::$requestCache[$cacheKey] = $cached;
                return $cached;
            }

            Log::info('LSTMClient: predict3Weeks() cache MISS — calling FastAPI', [
                'cache_key'  => $cacheKey,
                'start_date' => $startDate,
            ]);

            $result = $this->executePredict3Weeks($timeSeries, $useDummyData);

            if ($result !== null) {
                // Store in both persistent and request-scoped cache
                Cache::put($cacheKey, $result, self::PREDICT_TTL);
                self::$requestCache[$cacheKey] = $result;
                
                $predictions = $result['predictions'] ?? [];
                $firstDate   = $predictions[0]['date'] ?? 'unknown';
                $lastDate    = $predictions[count($predictions) - 1]['date'] ?? 'unknown';
                Log::info('LSTMClient: predict3Weeks() fresh result cached', [
                    'cache_key'        => $cacheKey,
                    'first_prediction' => $firstDate,
                    'last_prediction'  => $lastDate,
                    'model_fingerprint'=> $fp,
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('LSTM 3-week prediction failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Execute the actual 3-week prediction call to FastAPI
     * Extracted to reduce code duplication
     */
    private function executePredict3Weeks(array $timeSeries, bool $useDummyData): ?array
    {
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

        $response = $this->http()->post($this->baseUrl . '/predict-3weeks', $payload);

        if (!$response->successful()) {
            Log::warning('3-week forecast request failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        return $response->json();
    }

    public function getModelMetrics(): ?array
    {
        return Cache::remember('lstm.model_metrics', self::METRICS_TTL, function () {
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
        });
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

            $this->bustModelCache();

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

        $windowSize      = AISettings::get('ma_window', 7);
        $weekendFactor   = AISettings::get('ma_weekend_factor', 0.9);
        $lowerMult       = AISettings::get('ma_lower_bound', 0.8);
        $upperMult       = AISettings::get('ma_upper_bound', 1.2);
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
