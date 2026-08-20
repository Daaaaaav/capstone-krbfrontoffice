<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WazuhService
{
    // -------------------------------------------------------------------------
    // Severity classification
    // Centralised here so both IT Officer and Manager pages share
    // identical logic without duplication.
    //
    // Mapping:
    //   Critical : level >= 12
    //   High     : level  9 – 11
    //   Medium   : level  6 – 8
    //   Low      : level  1 – 5
    //   (Level 0 is intentionally excluded from counts)
    // -------------------------------------------------------------------------

    public function classifySeverity(int $level): string
    {
        return match (true) {
            $level >= 12 => 'critical',
            $level >= 9  => 'high',
            $level >= 6  => 'medium',
            $level >= 1  => 'low',
            default      => 'unknown',
        };
    }

    public function severityLabel(int $level): string
    {
        return match ($this->classifySeverity($level)) {
            'critical' => 'Critical',
            'high'     => 'High',
            'medium'   => 'Medium',
            'low'      => 'Low',
            default    => 'Unknown',
        };
    }

    public function severityBadgeClass(int $level): string
    {
        return match ($this->classifySeverity($level)) {
            'critical' => 'bg-red-100 text-red-800 border-red-300 font-bold',
            'high'     => 'bg-orange-100 text-orange-800 border-orange-300',
            'medium'   => 'bg-yellow-100 text-yellow-800 border-yellow-300',
            'low'      => 'bg-green-100 text-green-800 border-green-300',
            default    => 'bg-gray-100 text-gray-800 border-gray-300',
        };
    }

    // -------------------------------------------------------------------------
    // Normalise a raw Wazuh Indexer _source hit into a flat, safe array.
    // All fields use data_get() with safe fallbacks so missing fields
    // do not crash the view.
    // -------------------------------------------------------------------------

    public function normalizeAlert(array $source): array
    {
        $level = (int) data_get($source, 'rule.level', 0);

        return [
            // Rule info
            'rule_level'       => $level,
            'rule_id'          => (string) data_get($source, 'rule.id', '-'),
            'rule_description' => (string) data_get($source, 'rule.description', 'Unknown alert'),

            // Agent info
            'agent_name'       => (string) data_get($source, 'agent.name', 'Unknown'),
            'agent_id'         => (string) data_get($source, 'agent.id', '-'),
            'agent_ip'         => (string) data_get($source, 'agent.ip', '-'),

            // Manager / decoder
            'manager_name'     => (string) data_get($source, 'manager.name', '-'),
            'decoder_name'     => (string) data_get($source, 'decoder.name', '-'),

            // Location / timestamp / log
            'location'         => (string) data_get($source, 'location', '-'),
            'timestamp'        => (string) data_get($source, '@timestamp', ''),

            // full_log is untrusted external data – kept as plain string only.
            // It MUST be rendered with {{ }} (escaped) in Blade, never {!! !!}.
            'full_log'         => (string) data_get($source, 'full_log', ''),

            // Computed severity helpers
            'severity'         => $this->classifySeverity($level),
            'severity_label'   => $this->severityLabel($level),
            'badge_class'      => $this->severityBadgeClass($level),
        ];
    }

    // -------------------------------------------------------------------------
    // getRecentAlerts – the original working method, preserved for existing callers.
    //
    // Returns [] on both "no alerts" and "connection failure".
    // Callers that need to distinguish those states should use getSecuritySummary().
    // -------------------------------------------------------------------------

    public function getRecentAlerts(int $limit = 10): array
    {
        try {
            $response = Http::withBasicAuth(
                config('services.wazuh.username'),
                config('services.wazuh.password')
            )
                ->withoutVerifying()
                ->timeout(10)
                ->post(
                    config('services.wazuh.url') . '/wazuh-alerts-*/_search',
                    [
                        'size' => $limit,
                        'sort' => [
                            [
                                '@timestamp' => [
                                    'order' => 'desc',
                                ],
                            ],
                        ],
                        'query' => [
                            'match_all' => new \stdClass(),
                        ],
                    ]
                );

            if ($response->failed()) {
                Log::error('Wazuh API request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return [];
            }

            return collect($response->json('hits.hits', []))
                ->map(fn ($hit) => $hit['_source'] ?? [])
                ->values()
                ->all();

        } catch (\Throwable $e) {
            Log::error('Wazuh connection error: ' . $e->getMessage());

            return [];
        }
    }

    // -------------------------------------------------------------------------
    // getSecuritySummary – used by the Security Reports pages.
    //
    // Fetches up to $limit recent alerts from the Wazuh Indexer, normalises them,
    // and calculates severity summary counts from the same bounded dataset
    // (no N+1 queries, no full history scan).
    //
    // Returns an array with:
    //   'available'    bool   – true if the Indexer responded, false on any failure
    //   'alerts'       array  – normalised alert rows (empty array if none)
    //   'summary'      array  – severity counts keyed by severity name
    //   'last_updated' string – ISO timestamp of when this data was fetched
    //
    // The caller can reliably distinguish:
    //   available=true  + count(alerts)>0  → display alerts
    //   available=true  + count(alerts)==0 → "No security alerts found."
    //   available=false                    → "Security monitoring temporarily unavailable."
    // -------------------------------------------------------------------------

    public function getSecuritySummary(int $limit = 50): array
    {
        $lastUpdated = now()->toIso8601String();

        try {
            $response = Http::withBasicAuth(
                config('services.wazuh.username'),
                config('services.wazuh.password')
            )
                ->withoutVerifying()
                ->timeout(15)
                ->post(
                    config('services.wazuh.url') . '/wazuh-alerts-*/_search',
                    [
                        'size' => $limit,
                        'sort' => [
                            ['@timestamp' => ['order' => 'desc']],
                        ],
                        'query' => [
                            'match_all' => new \stdClass(),
                        ],
                    ]
                );

            if ($response->failed()) {
                Log::error('WazuhService::getSecuritySummary failed', [
                    'status' => $response->status(),
                    // body intentionally truncated – do not log full body as it may contain sensitive data
                    'body_preview' => substr($response->body(), 0, 200),
                ]);

                return $this->unavailableResponse($lastUpdated);
            }

            $rawHits = $response->json('hits.hits', []);

            // Normalise every hit into a safe flat array
            $alerts = collect($rawHits)
                ->map(fn ($hit) => $this->normalizeAlert($hit['_source'] ?? []))
                ->values()
                ->all();

            // Calculate summary counts from the same bounded dataset
            $summary = $this->buildSummaryFromAlerts($alerts);

            return [
                'available'    => true,
                'alerts'       => $alerts,
                'summary'      => $summary,
                'last_updated' => $lastUpdated,
            ];

        } catch (\Throwable $e) {
            Log::error('WazuhService::getSecuritySummary exception: ' . $e->getMessage(), [
                'class' => get_class($e),
                // stack trace intentionally omitted from log to avoid leaking
                // internal paths; use the log channel's stack trace if needed
            ]);

            return $this->unavailableResponse($lastUpdated);
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build severity summary counts from an already-normalised alerts array.
     * Counts are based on the current bounded dataset, not full history.
     */
    private function buildSummaryFromAlerts(array $alerts): array
    {
        $counts = [
            'total'    => count($alerts),
            'critical' => 0,
            'high'     => 0,
            'medium'   => 0,
            'low'      => 0,
        ];

        foreach ($alerts as $alert) {
            $severity = $alert['severity'] ?? 'unknown';
            if (isset($counts[$severity])) {
                $counts[$severity]++;
            }
        }

        return $counts;
    }

    /**
     * Standard unavailable response – used when the Indexer is offline,
     * credentials fail, times out, or returns an unexpected structure.
     *
     * Returning available=false lets the view show "temporarily unavailable"
     * instead of incorrectly showing "no alerts found".
     */
    private function unavailableResponse(string $lastUpdated): array
    {
        return [
            'available'    => false,
            'alerts'       => [],
            'summary'      => [
                'total'    => 0,
                'critical' => 0,
                'high'     => 0,
                'medium'   => 0,
                'low'      => 0,
            ],
            'last_updated' => $lastUpdated,
        ];
    }
}
