<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApplicationHealthService
{
    public const CACHE_KEY = 'krb_application_health_status';
    public const CACHE_TTL_SECONDS = 30;
    public const REQUEST_TIMEOUT_SECONDS = 4;

    /**
     * Get health summary across all monitored endpoints.
     *
     * @param bool $forceRefresh Bypass cache if true
     * @return array
     */
    public function getHealthSummary(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return $this->performHealthChecks();
        });
    }

    /**
     * Perform actual health checks against all endpoints.
     */
    public function performHealthChecks(): array
    {
        $checkedAt = now()->toIso8601String();

        $laravelLocal = $this->checkEndpoint(
            id: 'krb_laravel_local',
            name: 'KRB Laravel Application',
            url: config('app.url') ? rtrim(config('app.url'), '/') . '/health' : 'http://127.0.0.1:8000/health',
            expectedStatus: 200,
            type: 'local'
        );

        // Fallback check on port 8000 if app.url is custom
        if (!$laravelLocal['is_healthy'] && config('app.url') !== 'http://127.0.0.1:8000') {
            $fallbackLocal = $this->checkEndpoint(
                id: 'krb_laravel_local',
                name: 'KRB Laravel Application',
                url: 'http://127.0.0.1:8000/health',
                expectedStatus: 200,
                type: 'local'
            );
            if ($fallbackLocal['is_healthy']) {
                $laravelLocal = $fallbackLocal;
            }
        }

        $lstmUrl = config('services.lstm.url', env('LSTM_SERVICE_URL', 'http://127.0.0.1:8001'));
        $lstmUrl = rtrim($lstmUrl, '/') . '/';
        $lstmLocal = $this->checkEndpoint(
            id: 'lstm_local',
            name: 'LSTM Forecast Service',
            url: $lstmUrl,
            expectedStatus: 200,
            type: 'local'
        );

        $krbPublic = $this->checkEndpoint(
            id: 'krb_public',
            name: 'KRB Public Endpoint',
            url: 'https://receptionistkebunraya.online/health',
            expectedStatus: 200,
            type: 'public'
        );

        $services = [
            'krb_laravel_local' => $laravelLocal,
            'lstm_local'        => $lstmLocal,
            'krb_public'        => $krbPublic,
        ];

        $healthyCount = 0;
        $totalCount   = count($services);

        foreach ($services as $srv) {
            if ($srv['is_healthy']) {
                $healthyCount++;
            }
        }

        // Determine overall health status
        if ($healthyCount === $totalCount) {
            $overallStatus = 'healthy';
            $overallLabel  = 'All Services Operational';
            $overallBadge  = 'bg-emerald-500/10 text-emerald-700 border-emerald-500/20 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/60';
        } elseif ($healthyCount > 0) {
            $overallStatus = 'degraded';
            $overallLabel  = 'Degraded Performance / Partial Outage';
            $overallBadge  = 'bg-amber-500/10 text-amber-700 border-amber-500/20 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/60';
        } else {
            $overallStatus = 'down';
            $overallLabel  = 'System Outage / Critical';
            $overallBadge  = 'bg-rose-500/10 text-rose-700 border-rose-500/20 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/60';
        }

        return [
            'status'         => $overallStatus,
            'status_label'   => $overallLabel,
            'status_badge'   => $overallBadge,
            'healthy_count'  => $healthyCount,
            'total_count'    => $totalCount,
            'last_checked'   => $checkedAt,
            'services'       => $services,
        ];
    }

    /**
     * Check an individual HTTP endpoint.
     */
    protected function checkEndpoint(string $id, string $name, string $url, int $expectedStatus = 200, string $type = 'local'): array
    {
        $startTime = microtime(true);
        $isHealthy = false;
        $statusCode = null;
        $message = 'Unknown';

        try {
            $response = Http::withoutVerifying()
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->connectTimeout(self::REQUEST_TIMEOUT_SECONDS)
                ->get($url);

            $latencyMs = round((microtime(true) - $startTime) * 1000, 1);
            $statusCode = $response->status();

            if ($statusCode === $expectedStatus) {
                $isHealthy = true;
                $message = "HTTP {$statusCode} OK";
            } else {
                $isHealthy = false;
                $message = "HTTP {$statusCode} Unexpected Status";
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 1);
            $isHealthy = false;
            $statusCode = 0;
            $message = 'Connection Refused / Unreachable';
        } catch (\Throwable $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 1);
            $isHealthy = false;
            $statusCode = 0;
            $message = 'Timeout / Network Error';
        }

        return [
            'id'               => $id,
            'name'             => $name,
            'url'              => $url,
            'type'             => $type,
            'is_healthy'       => $isHealthy,
            'status'           => $isHealthy ? 'healthy' : 'down',
            'status_label'     => $isHealthy ? 'Healthy' : 'Down',
            'status_badge'     => $isHealthy
                ? 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300'
                : 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950 dark:text-rose-300',
            'response_code'    => $statusCode,
            'response_time_ms' => $latencyMs,
            'message'          => $message,
        ];
    }
}