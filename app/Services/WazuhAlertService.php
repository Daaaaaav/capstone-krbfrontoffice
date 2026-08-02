<?php

namespace App\Services;

use Carbon\Carbon;

class WazuhAlertService
{
    private const DEFAULT_WAZUH_ALERT_PATH = '/var/ossec/logs/alerts/alerts.log';

    public function getRecentAlerts(int $limit = 25, string $severity = 'all'): array
    {
        $source = $this->resolveSourcePath();

        if (!is_readable($source)) {
            return [
                'source' => $source,
                'source_label' => 'Unavailable',
                'source_host' => $this->resolveSourceHost(),
                'api_endpoints' => [],
                'last_updated' => null,
                'alerts' => [],
                'stats' => $this->emptyStats(),
                'total_count' => 0,
                'available' => false,
            ];
        }

        $lines = $this->tailLines($source, 5000);

        $allAlerts = [];
        foreach (array_reverse($lines) as $line) {
            $allAlerts[] = $this->parseAlertLine($line);
        }

        $stats = $this->buildStats($allAlerts);
        $totalCount = count($allAlerts);

        $displayAlerts = [];
        foreach ($allAlerts as $alert) {
            if ($severity !== 'all' && $alert['severity'] !== $severity) {
                continue;
            }
            $displayAlerts[] = $alert;
            if (count($displayAlerts) >= $limit) {
                break;
            }
        }

        return [
            'source' => $source,
            'source_label' => $this->sourceLabel($source),
            'source_host' => $this->resolveSourceHost(),
            'api_endpoints' => [],
            'last_updated' => Carbon::createFromTimestamp(filemtime($source) ?: time())->toDateTimeString(),
            'alerts' => $displayAlerts,
            'stats' => $stats,
            'total_count' => $totalCount,
            'available' => true,
        ];
    }

    public function resolveSourcePath(): string
    {
        $configured = (string) env('WAZUH_ALERT_LOG_PATH', self::DEFAULT_WAZUH_ALERT_PATH);

        if (is_readable($configured)) {
            return $configured;
        }

        $laravelLog = storage_path('logs/laravel.log');

        if (is_readable($laravelLog)) {
            return $laravelLog;
        }

        return $configured;
    }

    private function tailLines(string $path, int $limit): array
    {
        $lines = [];
        $file = new \SplFileObject($path, 'r');

        while (!$file->eof()) {
            $line = trim((string) $file->fgets());

            if ($line === '') {
                continue;
            }

            $lines[] = $line;

            if (count($lines) > $limit) {
                array_shift($lines);
            }
        }

        return $lines;
    }

    private function parseAlertLine(string $line): array
    {
        $raw = trim($line);
        $parsed = [
            'raw' => $raw,
            'timestamp' => null,
            'rule_id' => null,
            'level' => null,
            'severity' => 'low',
            'severity_label' => 'Low',
            'title' => 'Wazuh alert',
            'message' => $raw,
            'source_ip' => null,
            'agent' => null,
            'location' => null,
            'details' => [],
        ];

        if (str_starts_with($raw, '{')) {
            $json = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $parsed = array_merge($parsed, $this->parseJsonAlert($json, $raw));
                return $parsed;
            }
        }

        if (preg_match('/^\[(?<timestamp>[^\]]+)\]\s*(?<message>.*)$/', $raw, $matches) === 1) {
            $parsed['timestamp'] = $matches['timestamp'];
            $raw = $matches['message'];
        }

        if (preg_match('/Rule:\s*(?<rule_id>\d+)/i', $raw, $matches) === 1) {
            $parsed['rule_id'] = $matches['rule_id'];
        }

        if (preg_match('/level\s*(?<level>\d+)/i', $raw, $matches) === 1) {
            $parsed['level'] = (int) $matches['level'];
            $parsed['severity'] = $this->severityFromLevel($parsed['level']);
            $parsed['severity_label'] = ucfirst($parsed['severity']);
        }

        if (preg_match('/(?:srcip|source ip|ip)[:=]\s*(?<ip>[0-9a-fA-F:.]+)/i', $raw, $matches) === 1) {
            $parsed['source_ip'] = $matches['ip'];
        }

        if (preg_match('/(?:agent|host)[:=]\s*(?<agent>[^,;]+)(?:[,;]|$)/i', $raw, $matches) === 1) {
            $parsed['agent'] = trim($matches['agent']);
        }

        if (preg_match('/(?:location|log)[:=]\s*(?<location>[^,;]+)(?:[,;]|$)/i', $raw, $matches) === 1) {
            $parsed['location'] = trim($matches['location']);
        }

        if (preg_match('/->\s*(?<message>.+)$/', $raw, $matches) === 1) {
            $parsed['message'] = trim($matches['message']);
        }

        if (preg_match('/(?<title>LOGIN_FAILED|LOGIN_SUCCESS|FORM_SUBMIT|SQLI_DETECTED|XSS_DETECTED|FORM_SPAM_DETECTED|BRUTE_FORCE_DETECTED|FILE_UPLOAD_ATTACK|COMMAND_INJECTION)/', $raw, $matches) === 1) {
            $parsed['title'] = str_replace('_', ' ', $matches['title']);
        }

        $parsed['details'] = array_values(array_filter([
            $parsed['rule_id'] ? 'Rule ' . $parsed['rule_id'] : null,
            $parsed['source_ip'] ? 'IP ' . $parsed['source_ip'] : null,
            $parsed['agent'] ? 'Agent ' . $parsed['agent'] : null,
            $parsed['location'] ? 'Location ' . $parsed['location'] : null,
        ]));

        return $parsed;
    }

    private function parseJsonAlert(array $json, string $raw): array
    {
        $rule = $json['rule'] ?? [];
        $agent = $json['agent'] ?? [];
        $data = $json['data'] ?? [];

        $level = isset($rule['level']) ? (int) $rule['level'] : null;
        $severity = $level !== null ? $this->severityFromLevel($level) : 'low';

        return [
            'raw' => $raw,
            'timestamp' => $json['timestamp'] ?? $json['time'] ?? null,
            'rule_id' => $rule['id'] ?? $json['rule_id'] ?? null,
            'level' => $level,
            'severity' => $severity,
            'severity_label' => ucfirst($severity),
            'title' => $rule['description'] ?? $json['description'] ?? 'Wazuh alert',
            'message' => $json['full_log'] ?? $json['message'] ?? $raw,
            'source_ip' => $json['srcip'] ?? $data['srcip'] ?? null,
            'agent' => $agent['name'] ?? $json['agent_name'] ?? null,
            'location' => $json['location'] ?? ($json['decoder']['name'] ?? null),
            'details' => array_values(array_filter([
                isset($rule['id']) ? 'Rule ' . $rule['id'] : null,
                isset($agent['name']) ? 'Agent ' . $agent['name'] : null,
                isset($json['srcip']) ? 'IP ' . $json['srcip'] : null,
            ])),
        ];
    }

    private function severityFromLevel(?int $level): string
    {
        if ($level === null) {
            return 'low';
        }

        if ($level >= 12) {
            return 'high';
        }

        if ($level >= 7) {
            return 'medium';
        }

        return 'low';
    }

    private function buildStats(array $alerts): array
    {
        return [
            ['label' => 'Total Alerts', 'value' => count($alerts), 'color' => 'blue'],
            ['label' => 'High Severity', 'value' => count(array_filter($alerts, fn (array $alert) => $alert['severity'] === 'high')), 'color' => 'red'],
            ['label' => 'Medium Severity', 'value' => count(array_filter($alerts, fn (array $alert) => $alert['severity'] === 'medium')), 'color' => 'yellow'],
            ['label' => 'Low Severity', 'value' => count(array_filter($alerts, fn (array $alert) => $alert['severity'] === 'low')), 'color' => 'green'],
        ];
    }

    private function emptyStats(): array
    {
        return [
            ['label' => 'Total Alerts', 'value' => 0, 'color' => 'blue'],
            ['label' => 'High Severity', 'value' => 0, 'color' => 'red'],
            ['label' => 'Medium Severity', 'value' => 0, 'color' => 'yellow'],
            ['label' => 'Low Severity', 'value' => 0, 'color' => 'green'],
        ];
    }

    private function sourceLabel(string $source): string
    {
        return str_contains($source, 'laravel.log') ? 'Laravel security log' : 'Wazuh alerts log';
    }

    private function resolveSourceHost(): ?string
    {
        $host = trim((string) env('WAZUH_MANAGER_HOST', ''));
        $port = trim((string) env('WAZUH_MANAGER_HOST_PORT', ''));

        if ($host === '' && $port === '') {
            return null;
        }

        if ($host === '') {
            $host = '127.0.0.1';
        }

        return $port !== '' ? $host . ':' . $port : $host;
    }
}