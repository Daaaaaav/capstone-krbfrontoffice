<?php

namespace App\Services;

use App\Models\WazuhAlert;
use Carbon\Carbon;

class WazuhAlertService
{
    public function getRecentAlerts(int $limit = 25, string $severity = 'all'): array
    {
        $query = WazuhAlert::latest();

        // 4-tier Severity Filtering
        if ($severity !== 'all') {
            switch ($severity) {
                case 'low':
                    $query->whereBetween('rule_level', [0, 3]);
                    break;
                case 'medium':
                    $query->whereBetween('rule_level', [4, 7]);
                    break;
                case 'high':
                    $query->whereBetween('rule_level', [8, 11]);
                    break;
                case 'critical':
                    $query->where('rule_level', '>=', 12);
                    break;
            }
        }

        $displayAlerts = $query->paginate($limit);
        $totalCount = WazuhAlert::count();

        // Build stats
        $stats = $this->buildStats();

        // The blade expects API endpoints and source host to be available, though they are less relevant now
        return [
            'source' => 'MySQL Database',
            'source_label' => 'Wazuh Webhook Integration',
            'source_host' => request()->getHost(),
            'api_endpoints' => ['POST /api/v1/wazuh-alerts'],
            'last_updated' => Carbon::now()->toDateTimeString(),
            'alerts' => $displayAlerts,
            'stats' => $stats,
            'total_count' => $totalCount,
            'available' => true,
        ];
    }

    private function buildStats(): array
    {
        $total = WazuhAlert::count();
        $critical = WazuhAlert::where('rule_level', '>=', 12)->count();
        $high = WazuhAlert::whereBetween('rule_level', [8, 11])->count();
        $medium = WazuhAlert::whereBetween('rule_level', [4, 7])->count();
        $low = WazuhAlert::whereBetween('rule_level', [0, 3])->count();

        return [
            ['label' => 'Total Alerts', 'value' => $total, 'color' => 'blue'],
            ['label' => 'Critical', 'value' => $critical, 'color' => 'red'],
            ['label' => 'High', 'value' => $high, 'color' => 'orange'],
            ['label' => 'Medium', 'value' => $medium, 'color' => 'yellow'],
            ['label' => 'Low', 'value' => $low, 'color' => 'green'],
        ];
    }
}