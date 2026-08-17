<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

#[Layout('layouts.manager')]
#[Title('Wazuh Security Reports')]
class AISecurityReports extends Component
{
    use WithPagination;

    public string $selectedSeverity = 'all';

    public bool $autoRefresh = true;

    public function setSeverity(string $level): void
    {
        $this->resetPage();
        $this->selectedSeverity = $level;
    }

    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    public function render()
    {
        try {
            $report = $this->fetchFromIndexer(10);

            return view(
                'livewire.pages.manager.a-i-security-reports',
                array_merge(['selectedSeverity' => $this->selectedSeverity], $report)
            );

        } catch (\Throwable $e) {
            Log::error('Failed to fetch Wazuh alerts', ['error' => $e->getMessage()]);

            return view(
                'livewire.pages.manager.a-i-security-reports',
                [
                    'alerts'           => [],
                    'stats'            => $this->buildEmptyStats(),
                    'total_count'      => 0,
                    'source_label'     => 'Wazuh Indexer (Error)',
                    'source_host'      => null,
                    'api_endpoints'    => [],
                    'last_updated'     => null,
                    'available'        => false,
                    'selectedSeverity' => $this->selectedSeverity,
                ]
            );
        }
    }

    private function fetchFromIndexer(int $perPage)
    {
        $wazuhIndexerIp = env('WAZUH_INDEXER_INTERNAL_IP', '10.0.0.50');
        $indexerUrl = "https://{$wazuhIndexerIp}:9200/wazuh-alerts-*/_search";
        $user = env('WAZUH_INDEXER_USER', env('WAZUH_API_USER', 'admin'));
        $pass = env('WAZUH_INDEXER_PASSWORD', env('WAZUH_API_PASS', 'SecretPassword'));
        
        $must = [];
        
        // Severity mapping
        if ($this->selectedSeverity !== 'all') {
            $range = match ($this->selectedSeverity) {
                'low' => ['gte' => 0, 'lte' => 3],
                'medium' => ['gte' => 4, 'lte' => 7],
                'high' => ['gte' => 8, 'lte' => 11],
                'critical' => ['gte' => 12],
                default => []
            };
            if (!empty($range)) {
                $must[] = ['range' => ['rule.level' => $range]];
            }
        }
        
        $queryBody = [
            'query' => [
                'bool' => [
                    'must' => $must
                ]
            ],
            'sort' => [
                ['@timestamp' => ['order' => 'desc']]
            ],
            'size' => 100 // Fetch up to 100 for simplicity and map them
        ];

        $response = Http::withBasicAuth($user, $pass)
            ->withoutVerifying()
            ->timeout(10)
            ->post($indexerUrl, $queryBody);

        if (!$response->successful()) {
            throw new \Exception('Non-200 response from Wazuh Indexer: ' . $response->status());
        }

        $hits = $response->json('hits.hits', []);
        $totalHits = $response->json('hits.total.value', 0);
        
        $alerts = collect($hits)->map(function ($hit) {
            return $this->formatAlert($hit['_source']);
        });

        // Pagination
        $currentPage = $this->getPage();
        $sliced = $alerts->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $paginator = new LengthAwarePaginator($sliced, $alerts->count(), $perPage, $currentPage, [
            'path' => request()->url(),
            'query' => request()->query()
        ]);
        
        // Calculate basic stats for the fetched 100 alerts
        $stats = $this->buildStats($alerts);

        return [
            'source' => 'Wazuh Indexer',
            'source_label' => 'Direct API Connection',
            'source_host' => $wazuhIndexerIp,
            'api_endpoints' => ["POST $indexerUrl"],
            'last_updated' => Carbon::now()->toDateTimeString(),
            'alerts' => $paginator,
            'stats' => $stats,
            'total_count' => $totalHits,
            'available' => true,
        ];
    }
    
    private function formatAlert(array $source)
    {
        $level = $source['rule']['level'] ?? 0;
        
        $severityLabel = 'Unknown';
        $severityBadgeClass = 'bg-gray-100 text-gray-800';
        
        if ($level >= 12) {
            $severityLabel = 'Critical';
            $severityBadgeClass = 'bg-red-100 text-red-800 border-red-200';
        } elseif ($level >= 8) {
            $severityLabel = 'High';
            $severityBadgeClass = 'bg-orange-100 text-orange-800 border-orange-200';
        } elseif ($level >= 4) {
            $severityLabel = 'Medium';
            $severityBadgeClass = 'bg-yellow-100 text-yellow-800 border-yellow-200';
        } else {
            $severityLabel = 'Low';
            $severityBadgeClass = 'bg-green-100 text-green-800 border-green-200';
        }

        return (object) [
            'rule_level' => $level,
            'severity_label' => $severityLabel,
            'severity_badge_class' => $severityBadgeClass,
            'description' => $source['rule']['description'] ?? 'No Description',
            'rule_id' => $source['rule']['id'] ?? '',
            'agent_name' => $source['agent']['name'] ?? 'Wazuh Manager',
            'created_at' => isset($source['@timestamp']) ? Carbon::parse($source['@timestamp']) : null,
            'raw_log' => $source['full_log'] ?? json_encode($source, JSON_PRETTY_PRINT),
        ];
    }
    
    private function buildStats($alerts)
    {
        $critical = $alerts->where('rule_level', '>=', 12)->count();
        $high = $alerts->whereBetween('rule_level', [8, 11])->count();
        $medium = $alerts->whereBetween('rule_level', [4, 7])->count();
        $low = $alerts->whereBetween('rule_level', [0, 3])->count();

        return [
            ['label' => 'Total Alerts', 'value' => $alerts->count(), 'color' => 'blue'],
            ['label' => 'Critical', 'value' => $critical, 'color' => 'red'],
            ['label' => 'High', 'value' => $high, 'color' => 'orange'],
            ['label' => 'Medium', 'value' => $medium, 'color' => 'yellow'],
            ['label' => 'Low', 'value' => $low, 'color' => 'green'],
        ];
    }

    private function buildEmptyStats()
    {
        return [
            ['label' => 'Total Alerts', 'value' => 0, 'color' => 'blue'],
            ['label' => 'Critical', 'value' => 0, 'color' => 'red'],
            ['label' => 'High', 'value' => 0, 'color' => 'orange'],
            ['label' => 'Medium', 'value' => 0, 'color' => 'yellow'],
            ['label' => 'Low', 'value' => 0, 'color' => 'green'],
        ];
    }
}
