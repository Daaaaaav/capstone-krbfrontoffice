<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SecurityMonitorController extends Controller
{
    public function index()
    {
        $wazuhIndexerIp = env('WAZUH_INDEXER_INTERNAL_IP', '10.0.0.50');
        $indexerUrl = "https://{$wazuhIndexerIp}:9200/wazuh-alerts-*/_search";
        
        try {
            $response = Http::withBasicAuth(env('WAZUH_API_USER'), env('WAZUH_API_PASS'))
                ->withoutVerifying() // Required if using Wazuh's internal self-signed certificates
                ->post($indexerUrl, [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['range' => ['rule.level' => ['gte' => 5]]]
                            ],
                            'filter' => [
                                ['range' => ['@timestamp' => ['gte' => 'now-24h']]]
                            ]
                        ]
                    ],
                    'sort' => [
                        ['@timestamp' => ['order' => 'desc']]
                    ],
                    'size' => 100 // Fetch up to 100 recent alerts
                ]);

            $alerts = [];
            
            if ($response->successful()) {
                $hits = $response->json()['hits']['hits'] ?? [];
                
                foreach ($hits as $hit) {
                    $alerts[] = $hit['_source']; 
                }
            } else {
                Log::error('Failed to connect to internal Wazuh Indexer API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while connecting to Wazuh Indexer API', [
                'message' => $e->getMessage()
            ]);
            $alerts = [];
        }

        return view('manager.security-dashboard', compact('alerts'));
    }
}
