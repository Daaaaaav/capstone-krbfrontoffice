<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;

/**
 * Communicates with the Wazuh Indexer REST API and returns structured alert data
 * for the security dashboards.
 *
 * This service owns ALL outbound HTTP traffic to the Wazuh stack.
 * It must not contain application-event logging; use SecurityMonitoringService for that.
 * Architecture reference: WAZUH_ARCHITECTURE.md section 1.
 */
class WazuhAlertService
{
    /**
     * Fetch and paginate alerts from the Wazuh Indexer.
     *
     * Keys in the returned array:
     *   source, source_label, source_host, api_endpoints, last_updated,
     *   alerts (LengthAwarePaginator), stats, total_count, available.
     *
     * @param  string  $selectedSeverity  'all' | 'low' | 'medium' | 'high' | 'critical'
     * @param  int     $currentPage       1-based page number
     * @param  int     $perPage           Alerts per page
     * @return array
     *
     * @throws \Exception  on non-200 response or connection failure
     */
    public function fetchAlerts(string $selectedSeverity, int $currentPage, int $perPage = 10): array
    {
        $ip   = config('services.wazuh.indexer_ip');
        $user = config('services.wazuh.indexer_user');
        $pass = config('services.wazuh.indexer_pass');

        if (empty($user) || empty($pass)) {
            throw new \RuntimeException('Wazuh Indexer credentials are not configured.');
        }

        $indexerUrl = "https://{$ip}:9200/wazuh-alerts-*/_search";

        $must = $this->buildSeverityFilter($selectedSeverity);

        $queryBody = [
            'query' => [
                'bool' => [
                    'must' => $must,
                ],
            ],
            'sort' => [
                ['@timestamp' => ['order' => 'desc']],
            ],
            // Fetch up to 100 for in-memory pagination and stat calculation
            'size' => 100,
        ];

        $response = Http::withBasicAuth($user, $pass)
            ->withoutVerifying()   // required for self-signed internal Wazuh certs
            ->timeout(10)
            ->post($indexerUrl, $queryBody);

        if (!$response->successful()) {
            throw new \Exception('Non-200 response from Wazuh Indexer: ' . $response->status());
        }

        $hits      = $response->json('hits.hits', []);
        $totalHits = $response->json('hits.total.value', 0);

        $alerts = collect($hits)->map(fn($hit) => $this->formatAlert($hit['_source']));

        $sliced    = $alerts->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $paginator = new LengthAwarePaginator($sliced, $alerts->count(), $perPage, $currentPage, [
            'path'  => request()->url(),
            'query' => request()->query(),
        ]);

        return [
            'source'        => 'Wazuh Indexer',
            'source_label'  => 'Direct API Connection',
            'source_host'   => $ip,
            'api_endpoints' => ["POST {$indexerUrl}"],
            'last_updated'  => Carbon::now()->toDateTimeString(),
            'alerts'        => $paginator,
            'stats'         => $this->buildStats($alerts),
            'total_count'   => $totalHits,
            'available'     => true,
        ];
    }

    /**
     * Build the Elasticsearch bool.must filter for a given severity label.
     *
     * @param  string  $severity
     * @return array
     */
    private function buildSeverityFilter(string $severity): array
    {
        if ($severity === 'all') {
            return [];
        }

        $range = match ($severity) {
            'low'      => ['gte' => 0,  'lte' => 3],
            'medium'   => ['gte' => 4,  'lte' => 7],
            'high'     => ['gte' => 8,  'lte' => 11],
            'critical' => ['gte' => 12],
            default    => [],
        };

        return empty($range) ? [] : [['range' => ['rule.level' => $range]]];
    }

    /**
     * Map a raw Wazuh Indexer hit source to a presentable object.
     *
     * @param  array  $source  The _source field of an Indexer hit
     * @return object
     */
    private function formatAlert(array $source): object
    {
        $level = $source['rule']['level'] ?? 0;

        [$severityLabel, $severityBadgeClass] = match (true) {
            $level >= 12 => ['Critical', 'bg-red-100 text-red-800 border-red-200'],
            $level >= 8  => ['High',     'bg-orange-100 text-orange-800 border-orange-200'],
            $level >= 4  => ['Medium',   'bg-yellow-100 text-yellow-800 border-yellow-200'],
            default      => ['Low',      'bg-green-100 text-green-800 border-green-200'],
        };

        return (object) [
            'rule_level'           => $level,
            'severity_label'       => $severityLabel,
            'severity_badge_class' => $severityBadgeClass,
            'description'          => $source['rule']['description'] ?? 'No Description',
            'rule_id'              => $source['rule']['id'] ?? '',
            'agent_name'           => $source['agent']['name'] ?? 'Wazuh Manager',
            'created_at'           => isset($source['@timestamp'])
                                          ? Carbon::parse($source['@timestamp'])
                                          : null,
            'raw_log'              => $source['full_log'] ?? json_encode($source, JSON_PRETTY_PRINT),
        ];
    }

    /**
     * Compute per-severity counts from a collection of formatted alerts.
     *
     * @param  \Illuminate\Support\Collection  $alerts
     * @return array
     */
    private function buildStats(\Illuminate\Support\Collection $alerts): array
    {
        return [
            ['label' => 'Total Alerts', 'value' => $alerts->count(),                                     'color' => 'blue'],
            ['label' => 'Critical',     'value' => $alerts->where('rule_level', '>=', 12)->count(),      'color' => 'red'],
            ['label' => 'High',         'value' => $alerts->whereBetween('rule_level', [8, 11])->count(), 'color' => 'orange'],
            ['label' => 'Medium',       'value' => $alerts->whereBetween('rule_level', [4, 7])->count(),  'color' => 'yellow'],
            ['label' => 'Low',          'value' => $alerts->whereBetween('rule_level', [0, 3])->count(),  'color' => 'green'],
        ];
    }

    /**
     * Zero-value stats array returned by the Livewire component on error so the
     * blade view always receives the expected data shape.
     *
     * @return array
     */
    public function buildEmptyStats(): array
    {
        return [
            ['label' => 'Total Alerts', 'value' => 0, 'color' => 'blue'],
            ['label' => 'Critical',     'value' => 0, 'color' => 'red'],
            ['label' => 'High',         'value' => 0, 'color' => 'orange'],
            ['label' => 'Medium',       'value' => 0, 'color' => 'yellow'],
            ['label' => 'Low',          'value' => 0, 'color' => 'green'],
        ];
    }
}
